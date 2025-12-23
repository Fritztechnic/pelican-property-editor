<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\SendPowerRequest;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Response;

#[Group('Server', weight: 2)]
class PowerController extends ClientApiController
{
    /**
     * PowerController constructor.
     */
    public function __construct(private DaemonServerRepository $repository)
    {
        parent::__construct();
    }

    /**
     * Send power action
     *
     * Send a power action to a server.
     *
     * @throws ConnectionException
     */
    public function index(SendPowerRequest $request, Server $server): Response
    {
        $signal = $request->input('signal');
        $this->repository->setServer($server)->power($signal);

        // If server is started via API, set a last_started_at to protect from immediate hibernation.
        if ($signal === 'start') {
            $settings = $server->settings ?? [];
            $plugin = $settings['server_hibernate'] ?? [];
            $plugin['last_started_at'] = now()->toDateTimeString();
            $settings['server_hibernate'] = $plugin;
            $server->settings = $settings;
            $server->save();
        }

        Activity::event(strtolower("server:power.{$request->input('signal')}"))->log();

        return $this->returnNoContent();
    }
}
