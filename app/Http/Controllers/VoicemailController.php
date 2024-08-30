<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Voicemails;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\OpenAIService;
use Illuminate\Validation\Rule;
use App\Models\VoicemailGreetings;
use App\Models\VoicemailDestinations;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\TextToSpeechRequest;
use App\Http\Requests\StoreVoicemailRequest;
use App\Http\Requests\UpdateVoicemailRequest;

class VoicemailController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Voicemails';
    protected $searchable = ['source', 'destination', 'message'];

    public function __construct()
    {
        $this->model = new Voicemails();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("voicemail_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('voicemails.index'),
                    'store' => route('voicemails.store'),
                    // 'select_all' => route('messages.select.all'),
                    // 'bulk_delete' => route('messages.bulk.delete'),
                    // 'bulk_update' => route('messages.bulk.update'),
                    'item_options' => route('voicemails.item.options'),
                ]
            ]
        );

        // $data = array();

        // $searchString = $request->get('search');

        // $domain_uuid = Session::get('domain_uuid');

        // $voicemails = Voicemails::where('domain_uuid', $domain_uuid)->orderBy('voicemail_id', 'asc');
        // if ($searchString) {
        //     $voicemails->where(function ($query) use ($searchString) {
        //         $query->where('voicemail_id', 'ilike', '%' . str_replace('-', '', $searchString) . '%')
        //             ->orWhere('voicemail_mail_to', 'ilike', '%' . str_replace('-', '', $searchString) . '%')
        //             ->orWhere('voicemail_description', 'ilike', '%' . str_replace('-', '', $searchString) . '%');
        //     });
        // }

        // $voicemails = $voicemails->paginate(10)->onEachSide(1);

        // $data['searchString'] = $searchString;

        // $data['voicemails'] = $voicemails;
        // return view('layouts.voicemails.list')
        //     ->with($data)
        //     ->with('permissions', $permissions);
    }

    /**
     *  Get data
     */
    public function getData($paginate = 50)
    {

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'voicemail_id'); // Default to 'voicemail_id'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to descending

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();
        $domainUuid = session('domain_uuid');
        $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
        $data->with(['extension' => function ($query) use ($domainUuid) {
            $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                ->where('domain_uuid', $domainUuid);
        }]);


        $data->select(
            'voicemail_uuid',
            'voicemail_id',
            'voicemail_mail_to',
            'voicemail_enabled',
            'voicemail_description',

        );

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
    }

    /**
     * @param $query
     * @param $value
     * @return void
     */
    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;
        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                $query->orWhere($field, 'ilike', '%' . $value . '%');
            }
        });
    }


    /**
     * Show the create voicemail form.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function create()
    {
        if (!userCheckPermission('voicemail_add') || !userCheckPermission('voicemail_edit')) {
            return redirect('/');
        }

        $voicemail = new Voicemails();
        $voicemail->voicemail_enabled = "true";
        $voicemail->voicemail_transcription_enabled = get_domain_setting('transcription_enabled_default');

        $vm_unavailable_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/greeting_1.wav');

        $vm_name_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/recorded_name.wav');


        $data = [];
        $data['voicemail'] = $voicemail;
        $data['vm_unavailable_file_exists'] = $vm_unavailable_file_exists;
        $data['vm_name_file_exists'] = $vm_name_file_exists;


        return view('layouts.voicemails.createOrUpdate')->with($data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  guid  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(Voicemails $voicemail)
    {
        //check permissions
        if (!userCheckPermission('voicemail_edit')) {
            return redirect('/');
        }

        $vm_unavailable_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/greeting_1.wav');

        $vm_name_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/recorded_name.wav');

        $data = array();
        $data['voicemail'] = $voicemail;
        $data['vm_unavailable_file_exists'] = $vm_unavailable_file_exists;
        $data['vm_name_file_exists'] = $vm_name_file_exists;
        $data['domain_voicemails'] = $voicemail->domain->voicemails;
        $data['voicemail_destinations'] = $voicemail->voicemail_destinations;

        return view('layouts.voicemails.createOrUpdate')->with($data);
    }

    public function store(StoreVoicemailRequest $request)
    {
        $inputs = $request->validated();

        try {
            $this->model->fill($inputs);

            // Save the model instance to the database
            $this->model->save();

            // Check if voicemail_copies is present and is an array
            if (isset($inputs['voicemail_copies']) && is_array($inputs['voicemail_copies'])) {
                // Prepare data for VoicemailDestinations
                foreach ($inputs['voicemail_copies'] as $copyUuid) {
                    // Create a new VoicemailDestinations instance and set the fields
                    $voicemailDestination = new VoicemailDestinations();
                    $voicemailDestination->voicemail_uuid = $this->model->voicemail_uuid; // Set the parent voicemail UUID
                    $voicemailDestination->voicemail_uuid_copy = $copyUuid; // Set the copy UUID

                    // Save the VoicemailDestinations instance
                    $voicemailDestination->save();
                }
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['New item created']]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create new item']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    function update(UpdateVoicemailRequest $request, $uuid)
    {
        $inputs = $request->validated();

        try {
            // Retrieve the item by ID from the route parameter
            $voicemail = $this->model->findOrFail($uuid);

            // Update the voicemail with the new inputs
            $voicemail->fill($inputs);

            // Save the updated voicemail to the database
            $voicemail->save();

            // Check if voicemail_copies is present and is an array
            if (isset($inputs['voicemail_copies']) && is_array($inputs['voicemail_copies'])) {
                // Delete existing voicemail copies for this voicemail
                VoicemailDestinations::where('voicemail_uuid', $voicemail->voicemail_uuid)->delete();

                // Prepare data for new VoicemailDestinations
                foreach ($inputs['voicemail_copies'] as $copyUuid) {
                    // Create a new VoicemailDestinations instance and set the fields
                    $voicemailDestination = new VoicemailDestinations();
                    $voicemailDestination->voicemail_uuid = $voicemail->voicemail_uuid; // Set the parent voicemail UUID
                    $voicemailDestination->voicemail_uuid_copy = $copyUuid; // Set the copy UUID

                    // Save the VoicemailDestinations instance
                    $voicemailDestination->save();
                }
            }

            //clear the destinations session array
            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Item updated successfully']]
            ], 200);  // 200 OK for successful update
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update item']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Upload a voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function uploadVoicemailGreeting(Request $request, Voicemails $voicemail)
    {

        $domain = Domain::where('domain_uuid', $voicemail->domain_uuid)->first();

        if ($request->greeting_type == "unavailable") {
            $filename = "greeting_1.wav";
            $path = $request->voicemail_unavailable_upload_file->storeAs(
                $domain->domain_name . '/' . $voicemail->voicemail_id,
                $filename,
                'voicemail'
            );
        } elseif ($request->greeting_type == "name") {
            $filename = "recorded_name.wav";
            $path = $request->voicemail_name_upload_file->storeAs(
                $domain->domain_name . '/' . $voicemail->voicemail_id,
                $filename,
                'voicemail'
            );
        }

        if (!Storage::disk('voicemail')->exists($path)) {
            return response()->json([
                'error' => 401,
                'message' => 'Failed to upload file'
            ]);
        }

        // Remove old greeting
        foreach ($voicemail->greetings as $greeting) {
            if ($greeting->filename = $filename) {
                $greeting->delete();
                break;
            }
        }

        if ($request->greeting_type == "unavailable") {
            // Save new greeting in the database
            $greeting = new VoicemailGreetings();
            $greeting->domain_uuid = Session::get('domain_uuid');
            $greeting->voicemail_id = $voicemail->voicemail_id;
            $greeting->greeting_id = 1;
            $greeting->greeting_name = "Greeting 1";
            $greeting->greeting_filename = $filename;
            $voicemail->greetings()->save($greeting);

            // Save default gretting ID
            $voicemail->greeting_id = 1;
            $voicemail->save();
        }

        return response()->json([
            'status' => "success",
            'voicemail' => $voicemail->voicemail_id,
            'filename' => $filename,
            'message' => 'Greeting uploaded successfully'
        ]);
    }


    /**
     * Get voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    // public function getVoicemailGreeting(Voicemails $voicemail, string $filename)
    // {
    //     $path = Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;

    //     if (!Storage::disk('voicemail')->exists($path)) abort(404);

    //     $file = Storage::disk('voicemail')->path($path);
    //     $type = Storage::disk('voicemail')->mimeType($path);

    //     $response = Response::make(file_get_contents($file), 200);
    //     $response->header("Content-Type", $type);
    //     return $response;
    // }

    /**
     * Get voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadVoicemailGreeting(Voicemails $voicemail, string $filename)
    {

        $path = Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;

        if (!Storage::disk('voicemail')->exists($path)) abort(404);

        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);
        $headers = array(
            'Content-Type: ' . $type,
        );

        $response = Response::download($file, $filename, $headers);

        return $response;
    }

    /**
     * Get voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteVoicemailGreeting(Voicemails $voicemail, string $filename)
    {

        $path = Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;

        $file = Storage::disk('voicemail')->delete($path);

        if (Storage::disk('voicemail')->exists($path)) {
            return response()->json([
                'error' => 401,
                'message' => 'Failed to delete file'
            ]);
        }

        // Remove greeting from database
        foreach ($voicemail->greetings as $greeting) {
            if ($greeting->filename = "greeting_1.wav") {
                $greeting->delete();
                break;
            }
        }

        // Update default gretting ID
        $voicemail->greeting_id = null;
        $voicemail->save();

        return response()->json([
            'status' => "success",
            'voicemail' => $voicemail->voicemail_id,
            'filename' => 'greeting_1.wav',
            'message' => 'Greeting deleted successfully'
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $voicemail = Voicemails::findOrFail($id);

        if (isset($voicemail)) {
            $deleted = $voicemail->delete();
            $filename = "recorded_name.wav";
            $path = Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;
            $file = Storage::disk('voicemail')->delete($path);
            $filename = "greeting_1.wav";
            $path = Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;
            $file = Storage::disk('voicemail')->delete($path);

            if ($deleted) {
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected vocemail extensions have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected voicemail extensions'
                    ]
                ]);
            }
        }
    }


    public function getItemOptions()
    {
        try {

            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');
            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request

            // Base navigation array without Greetings
            $navigation = [
                [
                    'name' => 'Settings',
                    'icon' => 'Cog6ToothIcon',
                    'slug' => 'settings',
                ],
                [
                    'name' => 'Advanced',
                    'icon' => 'AdjustmentsHorizontalIcon',
                    'slug' => 'advanced',
                ],
            ];

            // Only add the Greetings tab if item_uuid exists and insert it in the second position
            if ($item_uuid) {
                $greetingsTab = [
                    'name' => 'Greetings',
                    'icon' => 'MusicalNoteIcon',
                    'slug' => 'greetings',
                ];

                // Insert Greetings tab at the second position (index 1)
                array_splice($navigation, 1, 0, [$greetingsTab]);
            }

            $voicemails =  $this->model::where($this->model->getTable() . '.domain_uuid', $domain_uuid)
                ->with(['extension' => function ($query) use ($domain_uuid) {
                    $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                        ->where('domain_uuid', $domain_uuid);
                }])
                ->select(
                    'voicemail_uuid',
                    'voicemail_id',
                    'voicemail_description',

                )
                ->orderBy('voicemail_id', 'asc')
                ->get();

            // Transform the collection into the desired array format
            $voicemailOptions = $voicemails->map(function ($voicemail) {
                return [
                    'value' => $voicemail->voicemail_uuid,
                    'name' => $voicemail->extension ? $voicemail->extension->name_formatted : $voicemail->voicemail_id . ' - Team Voicemail',
                ];
            })->toArray();


            // Check if item_uuid exists to find an existing voicemail
            if ($item_uuid) {
                // Find existing voicemail by item_uuid
                $voicemail = Voicemails::with([
                    'voicemail_destinations' => function ($query) {
                        $query->select('voicemail_destination_uuid', 'voicemail_uuid', 'voicemail_uuid_copy');
                    },
                    'greetings' => function ($query) use ($domain_uuid) {
                        $query->select('voicemail_id', 'greeting_id', 'greeting_name')
                            ->where('domain_uuid', $domain_uuid);
                    }
                ])->where('voicemail_uuid', $item_uuid)->first();


                // If a voicemail exists, use it; otherwise, create a new one
                if (!$voicemail) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                // Transform greetings into the desired array format
                $greetingsArray = $voicemail->greetings
                    ->sortBy('greeting_id')
                    ->map(function ($greeting) {
                        return [
                            'value' => $greeting->greeting_id,
                            'name' => $greeting->greeting_name,
                        ];
                    })->toArray();

                // Add the default options at the beginning of the array
                array_unshift(
                    $greetingsArray,
                    ['value' => '0', 'name' => 'None'],
                    ['value' => '-1', 'name' => 'System Default']
                );

                // Define the update route
                $updateRoute = route('voicemails.update', ['voicemail' => $item_uuid]);
            } else {
                // Create a new voicemail if item_uuid is not provided
                $voicemail = $this->model;
                $voicemail->voicemail_id = $voicemail->generateUniqueSequenceNumber();
                $voicemail->voicemail_password = $voicemail->voicemail_id;
                $voicemail->voicemail_file = get_domain_setting('voicemail_file');
                $voicemail->voicemail_local_after_email = get_domain_setting('keep_local');
                $voicemail->voicemail_transcription_enabled = get_domain_setting('transcription_enabled_default');
                $voicemail->voicemail_tutorial = 'false';
                $voicemail->voicemail_enabled = 'true';
                $voicemail->voicemail_recording_instructions = 'true';
            }

            $permissions = $this->getUserPermissions();
            // logger($permissions);

            // Extract voicemail_destinations and format it for frontend
            $voicemailCopies = [];
            if ($voicemail->voicemail_destinations) {
                $voicemailCopies = $voicemail->voicemail_destinations->map(function ($destination) {
                    return [
                        'value' => $destination->voicemail_uuid_copy, // Set the value to voicemail_uuid_copy
                        'name' => ''
                    ];
                })->toArray();
            }

            $openAiVoices = [
                ['value' => 'alloy', 'name' => 'Alloy'],
                ['value' => 'echo', 'name' => 'Echo'],
                ['value' => 'fable', 'name' => 'Fable'],
                ['value' => 'onyx', 'name' => 'Onyx'],
                ['value' => 'nova', 'name' => 'Nova'],
                ['value' => 'shimmer', 'name' => 'Shimmer'],
            ];

            $openAiSpeeds = [];

            for ($i = 0.25; $i <= 4.0; $i += 0.25) {
                if (floor($i) == $i) {
                    // Whole number, format with one decimal place
                    $formattedValue = sprintf('%.1f', $i);
                } else {
                    // Fractional number, format with two decimal places
                    $formattedValue = sprintf('%.2f', $i);
                }
                $openAiSpeeds[] = ['value' => $formattedValue, 'name' => $formattedValue];
            }

            $routes = [
                'text_to_speech_route' => route('voicemails.textToSpeech', $voicemail),
                'text_to_speech_route_for_name' => route('voicemails.textToSpeechForName', $voicemail),
                'greeting_route' => route('voicemail.greeting', $voicemail),
                'delete_greeting_route' => route('voicemails.deleteGreeting', $voicemail),
                'upload_greeting_route' => route('voicemails.uploadGreeting', $voicemail),
                'upload_greeting_route_for_name' => route('voicemails.uploadRecordedName', $voicemail),
                'recorded_name_route' => route('voicemail.recorded_name', $voicemail),
                'delete_recorded_name_route' => route('voicemails.deleteRecordedName', $voicemail),
                'upload_recorded_name_route' => route('voicemails.uploadRecordedName', $voicemail),
            ];

            // Define the instructions for recording a voicemail greeting using a phone call
            $phoneCallInstructions = [
                'Dial <strong>*98</strong> from your phone.',
                'Enter the mailbox number and press <strong>#</strong>.',
                'Enter the voicemail password and press <strong>#</strong>.',
                'Press <strong>5</strong> for mailbox options.',
                'Press <strong>1</strong> to record an unavailable message.',
                'Choose a greeting number (1-9) to record, then follow the prompts.',
            ];

            // Define the instructions for recording a name using a phone call
            $phoneCallInstructionsForName = [
                'Dial <strong>*98</strong> from your phone.',
                'Enter the mailbox number and press <strong>#</strong>.',
                'Enter the voicemail password and press <strong>#</strong>.',
                'Press <strong>5</strong> for mailbox options.',
                'Press <strong>3</strong> to record your name, then follow the prompts.',
            ];

            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'all_voicemails' => $voicemailOptions,
                'voicemail' => $voicemail,
                'permissions' => $permissions,
                'voicemail_copies' => $voicemailCopies,
                'greetings' => $greetingsArray,
                'voices' => $openAiVoices,
                'speeds' => $openAiSpeeds,
                'routes' => $routes,
                'phone_call_instructions' => $phoneCallInstructions,
                'phone_call_instructions_for_name' => $phoneCallInstructionsForName,
                'recorded_name' => Storage::disk('voicemail')->exists(session('domain_name') . '/' . $voicemail->voicemail_id . '/recorded_name.wav') ? 'Custom recording' : 'System Default',
                // Define options for other fields as needed
            ];

            // Include the update route if item_uuid exists
            if ($item_uuid) {
                $itemOptions['update_route'] = $updateRoute;
            }

            return $itemOptions;
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch item details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['manage_voicemail_copies'] = userCheckPermission('voicemail_forward');
        $permissions['manage_voicemail_transcription'] = userCheckPermission('voicemail_transcription_enabled');
        $permissions['manage_voicemail_auto_delete'] = userCheckPermission('voicemail_local_after_email');
        $permissions['manage_voicemail_recording_instructions'] = userCheckPermission('voicemail_recording_instructions');

        // $permissions['manage_voicemail_copies'] = false;
        // $permissions['manage_voicemail_transcription'] = false;
        // $permissions['manage_voicemail_auto_delete'] = false;
        // $permissions['manage_voicemail_recording_instructions'] = false;

        return $permissions;
    }

    public function textToSpeech(Voicemails $voicemail, OpenAIService $openAIService, TextToSpeechRequest $request)
    {
        $input = $request->input('input');
        $model = $request->input('model');
        $voice = $request->input('voice');
        $responseFormat = $request->input('response_format');
        $speed = $request->input('speed');

        try {
            $response = $openAIService->textToSpeech($model, $input, $voice, $responseFormat, $speed);

            $domainName = session('domain_name');

            // Delete all temp files
            $this->deleteTempFiles($domainName . '/' . $voicemail->voicemail_id);

            $fileName = 'temp_' . now()->format('Ymd_His') . '.' . $responseFormat; // Generates filename like temp_20240826_153045.wav
            $filePath = $domainName . '/' . $voicemail->voicemail_id . '/' . $fileName;

            // Save file to the voicemail disk with domain folder
            Storage::disk('voicemail')->put($filePath, $response);

            // Generate the file URL using the defined route
            $fileUrl = route('voicemail.file.serve', [
                'domain' => $domainName,
                'voicemail_id' => $voicemail->voicemail_id,
                'file' => $fileName,
            ]);

            // Generate the file URL using the defined route
            $applyUrl = route('voicemail.file.apply', [
                'domain' => $domainName,
                'voicemail' => $voicemail,
                'file' => $fileName,
            ]);

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
                'apply_url' => $applyUrl,
            ]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function textToSpeechForName(Voicemails $voicemail, OpenAIService $openAIService, TextToSpeechRequest $request)
    {
        $input = $request->input('input');
        $model = $request->input('model');
        $voice = $request->input('voice');
        $responseFormat = $request->input('response_format');
        $speed = $request->input('speed');

        try {
            $response = $openAIService->textToSpeech($model, $input, $voice, $responseFormat, $speed);

            $domainName = session('domain_name');

            // Delete all temp files
            $this->deleteTempFiles($domainName . '/' . $voicemail->voicemail_id);

            $fileName = 'temp_' . now()->format('Ymd_His') . '.' . $responseFormat; // Generates filename like temp_20240826_153045.wav
            $filePath = $domainName . '/' . $voicemail->voicemail_id . '/' . $fileName;

            // Save file to the voicemail disk with domain folder
            Storage::disk('voicemail')->put($filePath, $response);

            // Generate the file URL using the defined route
            $fileUrl = route('voicemail.file.serve', [
                'domain' => $domainName,
                'voicemail_id' => $voicemail->voicemail_id,
                'file' => $fileName,
            ]);

            // Generate the file URL using the defined route
            $applyUrl = route('voicemail.file.name.apply', [
                'domain' => $domainName,
                'voicemail' => $voicemail,
                'file' => $fileName,
            ]);

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
                'apply_url' => $applyUrl,
            ]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function serveVoicemailFile($domain, $voicemail_id, $file)
    {
        $filePath = "{$domain}/{$voicemail_id}/{$file}";

        if (!Storage::disk('voicemail')->exists($filePath)) {
            // File not found
            return response()->json([
                'success' => false,
                'errors' => ['server' => 'File not found']
            ], 500);  // 500 Internal Server Error for any other errors
        }

        // Check if the 'download' parameter is present and set to true
        $download = request()->query('download', false);

        if ($download) {
            // Serve the file as a download
            return response()->download(Storage::disk('voicemail')->path($filePath));
        }

        // Serve the file inline
        return response()->file(Storage::disk('voicemail')->path($filePath));
    }

    public function applyVoicemailFile($domain, Voicemails $voicemail, $file)
    {
        try {
            $filePath = "{$domain}/{$voicemail->voicemail_id}/{$file}";

            if (!Storage::disk('voicemail')->exists($filePath)) {
                abort(404); // File not found
            }

            if (!Storage::disk('voicemail')->exists($filePath)) {
                // File not found
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['File not found']]
                ], 500);  // 500 Internal Server Error for any other errors
            }

            // Step 3: Find the next greeting_id to use
            $existingIds = $voicemail->greetings()
                ->pluck('greeting_id')
                ->sort()
                ->toArray();

            $nextId = 1; // Start from 0 or your desired starting ID

            foreach ($existingIds as $id) {
                if ($id == $nextId) {
                    $nextId++;
                } else {
                    break; // Found a gap
                }
            }

            // Step 4: Generate new greeting_id and filename
            $newGreetingId = $nextId;
            $newFileName = "greeting_{$newGreetingId}.wav";

            // Step 5: Construct the new file path
            $newFilePath = "{$domain}/{$voicemail->voicemail_id}/{$newFileName}";

            // Step 6: Store the file with the new name (you might want to copy instead of move)
            if (!Storage::disk('voicemail')->move($filePath, $newFilePath)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Failed to save the file']]
                ], 500);
            }

            // Step 7: Save greeting info to the database
            $voicemail->greetings()->create([
                'domain_uuid' => $voicemail->domain_uuid,
                'voicemail_id' => $voicemail->voicemail_id,
                'greeting_id' => $newGreetingId,
                'greeting_name' => "AI Greeting " . date('Ymd_His'),
                'greeting_filename' => $newFileName,
                'greeting_description' => "Generated greeting {$newGreetingId}",

            ]);

            // Step 8: Update the voicemail table with the new greeting_id
            $voicemail->update([
                'greeting_id' => $newGreetingId
            ]);

            return response()->json([
                'success' => true,
                'greeting_id' => $newGreetingId,
                'greeting_name' => "AI Greeting " . date('Ymd_His'),
                'message' => ['success' => 'Your AI-generated greeting has been saved and successfully activated.']
            ], 200);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function applyVoicemailFileForName($domain, Voicemails $voicemail, $file)
    {
        try {
            $filePath = "{$domain}/{$voicemail->voicemail_id}/{$file}";

            if (!Storage::disk('voicemail')->exists($filePath)) {
                abort(404); // File not found
            }

            if (!Storage::disk('voicemail')->exists($filePath)) {
                // File not found
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['File not found']]
                ], 500);  // 500 Internal Server Error for any other errors
            }


            // Step 4: Generate new filename
            $newFileName = "recorded_name.wav";

            // Step 5: Construct the new file path
            $newFilePath = "{$domain}/{$voicemail->voicemail_id}/{$newFileName}";

            // Step 6: Store the file with the new name (you might want to copy instead of move)
            if (!Storage::disk('voicemail')->move($filePath, $newFilePath)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Failed to save the file']]
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => ['success' => 'Your AI-generated recorded name has been saved and successfully activated.']
            ], 200);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }


    public function deleteTempFiles($folderPath)
    {
        $files = Storage::disk('voicemail')->files($folderPath);
        foreach ($files as $file) {
            if (Str::startsWith(basename($file), 'temp')) {
                Storage::disk('voicemail')->delete($file);
            }
        }
    }

    public function getVoicemailGreeting(Voicemails $voicemail)
    {

        try {
            // Step 1: Get the greeting_id from the request
            $greetingId = request('greeting_id');

            // Step 2: Fetch the greeting info from the database using the greeting_id and voicemail_id
            $greeting = $voicemail->greetings()
                ->where('greeting_id', $greetingId)
                ->first();

            // Check if the greeting exists
            if (!$greeting) {
                throw new \Exception('File not found');
            }

            // Generate the file URL using the defined route
            $fileUrl = route('voicemail.file.serve', [
                'domain' => session('domain_name'),
                'voicemail_id' => $voicemail->voicemail_id,
                'file' => $greeting->greeting_filename,
            ]);

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
            ]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }


    public function deleteGreeting(Voicemails $voicemail, Request $request)
    {
        try {
            $greetingId = $request->input('greeting_id');

            // Fetch the greeting to delete
            $greeting = $voicemail->greetings()->where('greeting_id', $greetingId)->first();

            if (!$greeting) {
                throw new \Exception('Greeting not found');
            }

            $filePath = session('domain_name') . '/' . $voicemail->voicemail_id . '/' . $greeting->greeting_filename;

            // Delete the greeting file from storage
            Storage::disk('voicemail')->delete($filePath);

            // Delete the greeting record from the database
            $greeting->delete();

            // Set voicemail greeting to System Default
            $voicemail->greeting_id = '-1';
            $voicemail->save();

            // Return a successful JSON response
            return response()->json([
                'success' => true,
                'message' => ['success' => 'Greeting has been removed.']
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json(['success' => false, 'errors' => ['server' => [$e->getMessage()]]], 500);
        }
    }

    public function uploadGreeting(Voicemails $voicemail, Request $request)
    {
        // Validate the file input
        $request->validate([
            'file' => 'required|mimes:wav,mp3|max:10240', // Limit to WAV or MP3 files, max size 10MB
        ]);

        $file = $request->file('file');
        $domainName = session('domain_name');

        try {
            // Find the next available greeting_id
            $existingIds = $voicemail->greetings()
                ->pluck('greeting_id')
                ->sort()
                ->toArray();

            $nextId = 1; // Start from 1 or your desired starting ID
            foreach ($existingIds as $id) {
                if ($id == $nextId) {
                    $nextId++;
                } else {
                    break; // Found a gap
                }
            }

            // Generate a unique filename based on the current time
            $fileName = 'greeting_' . $nextId . '.' . $file->getClientOriginalExtension();

            // Save file to the voicemail disk with domain folder
            Storage::disk('voicemail')->putFileAs($domainName . '/' . $voicemail->voicemail_id, $file, $fileName);


            // Save greeting info to the database
            $voicemail->greetings()->create([
                'domain_uuid' => $voicemail->domain_uuid,
                'voicemail_id' => $voicemail->voicemail_id,
                'greeting_id' => $nextId,
                'greeting_name' => "Uploaded File " . date('Ymd_His'),
                'greeting_filename' => $fileName,
                'greeting_description' => "Uploaded greeting {$nextId}",
            ]);

            // Update the voicemail table with the new greeting_id
            $voicemail->update([
                'greeting_id' => $nextId
            ]);



            // Return a successful JSON response
            return response()->json([
                'success' => true,
                'greeting_id' => $nextId,
                'greeting_name' => "Uploaded File " . date('Ymd_His'),
                'message' => ['success' => 'Greeting has been successfully uploaded and activated.']
            ], 200);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function getRecordedName(Voicemails $voicemail)
    {
        try {
            $filePath = session('domain_name') . '/' . $voicemail->voicemail_id . '/recorded_name.wav';

            if (!Storage::disk('voicemail')->exists($filePath)) {
                throw new \Exception('File not found');
            }

            $fileUrl = route('voicemail.file.serve', [
                'domain' => session('domain_name'),
                'voicemail_id' => $voicemail->voicemail_id,
                'file' => 'recorded_name.wav',
            ]);

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
            ]);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json(['success' => false, 'errors' => ['server' => [$e->getMessage()]]], 500);
        }
    }

    public function deleteRecordedName(Voicemails $voicemail)
    {
        try {
            $filePath = session('domain_name') . '/' . $voicemail->voicemail_id . '/recorded_name.wav';

            if (!Storage::disk('voicemail')->exists($filePath)) {
                throw new \Exception('File not found');
            }

            Storage::disk('voicemail')->delete($filePath);

            return response()->json([
                'success' => true,
                'message' => ['success' => 'Recorded name has been deleted.']
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json(['success' => false, 'errors' => ['server' => [$e->getMessage()]]], 500);
        }
    }
}
