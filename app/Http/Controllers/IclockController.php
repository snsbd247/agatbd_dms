<?php

namespace App\Http\Controllers;

use App\Models\Hr\AttendanceDevice;
use App\Services\Hr\DeviceAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The "push" (ADMS / iclock) protocol spoken by ZKTeco attendance machines.
 * Set the machine's Cloud Server to this site (port 443, HTTPS) and it
 * checks in every few seconds and uploads new punches by itself — the
 * server never has to reach into the office network.
 *
 *   GET  /iclock/cdata?SN=…           handshake, we answer with options
 *   POST /iclock/cdata?SN=…&table=ATTLOG   punches, one per line
 *   GET  /iclock/getrequest?SN=…      "any commands for me?" — none
 *   POST /iclock/devicecmd?SN=…       command results — ignored
 *
 * These routes have no session or CSRF (a machine cannot log in). Only
 * registered, active serial numbers are accepted; an unknown machine is
 * added inactive so an admin can switch it on in HR → Attendance Machines.
 */
class IclockController extends Controller
{
    /** Unknown machines added while waiting for approval, at most. */
    private const MAX_PENDING = 10;

    public function cdata(Request $request, DeviceAttendanceService $service): Response
    {
        $device = $this->device($request);
        if (! $device) {
            return $this->text('OK');
        }

        if ($request->isMethod('get')) {
            return $this->text(implode("\n", [
                'GET OPTION FROM: '.$device->serial_no,
                'ATTLOGStamp=None',
                'OPERLOGStamp=9999',
                'ATTPHOTOStamp=None',
                'ErrorDelay=60',
                'Delay=30',
                'TransTimes=00:00;14:05',
                'TransInterval=1',
                'TransFlag=TransData AttLog',
                'TimeZone=6',
                'Realtime=1',
                'Encrypt=None',
            ]));
        }

        if (strtoupper((string) $request->query('table')) === 'ATTLOG') {
            $count = $service->ingest($device, (string) $request->getContent());

            return $this->text('OK: '.$count);
        }

        return $this->text('OK');   // user lists, operation logs, photos: not used
    }

    public function getrequest(Request $request): Response
    {
        $this->device($request);

        return $this->text('OK');
    }

    public function devicecmd(Request $request): Response
    {
        $this->device($request);

        return $this->text('OK');
    }

    /** The active machine sending this request, noting that it was seen. */
    private function device(Request $request): ?AttendanceDevice
    {
        $serial = substr(trim((string) $request->query('SN', '')), 0, 40);
        if ($serial === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $serial)) {
            return null;
        }

        $device = AttendanceDevice::where('serial_no', $serial)->first();

        if (! $device) {
            if (AttendanceDevice::where('active', false)->where('punch_count', 0)->count() < self::MAX_PENDING) {
                AttendanceDevice::create(['serial_no' => $serial, 'name' => __('New machine').' '.$serial, 'active' => false, 'last_seen_at' => now(), 'last_ip' => $request->ip()]);
            }

            return null;
        }

        $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->save();

        return $device->active ? $device : null;
    }

    private function text(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'text/plain']);
    }
}
