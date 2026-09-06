<?php

namespace App\Services\Ivr;

use Carbon\Carbon;

class BulkSmsPlansIvrAdapter
{
    public const PROVIDER = 'bulksmsplans';

    public function normalize(array $payload): array
    {
        $startTime = $this->parseDateTime($this->first($payload, [
            'start_time', 'starttime', 'call_starttime', 'call_start_time', 'call_date', 'date',
        ]));
        $endTime = $this->parseDateTime($this->first($payload, [
            'end_time', 'endtime', 'call_endtime', 'call_end_time',
        ]));
        $duration = $this->parseDuration($this->first($payload, [
            'duration', 'call_duration', 'billsec', 'answered_seconds', 'talktime',
        ]));

        if ($duration === null && $startTime && $endTime) {
            $duration = $endTime->diffInSeconds($startTime);
        }

        return [
            'provider' => self::PROVIDER,
            'call_id' => (string) ($this->first($payload, [
                'call_id', 'callid', 'uuid', 'uniqueid', 'request_id', 'id',
            ]) ?: ('bulksmsplans_' . sha1(json_encode($payload) . microtime(true)))),
            'customer_phone' => $this->normalizePhone((string) $this->first($payload, [
                'customer_phone', 'receiver_number', 'caller', 'caller_number', 'callto', 'to', 'mobile', 'phone', 'client_number',
            ])),
            'agent_phone' => $this->normalizePhone((string) $this->first($payload, [
                'agent_phone', 'emp_phone', 'receiver', 'agent_number', 'employee_phone', 'destination',
            ])),
            'agent_name' => trim((string) $this->first($payload, [
                'agent_name', 'agentname', 'employee_name', 'receiver_name',
            ])),
            'call_status' => strtoupper(trim((string) $this->first($payload, [
                'call_status', 'dialstatus', 'status', 'disposition', 'call_result',
            ]))),
            'direction' => strtolower(trim((string) ($this->first($payload, [
                'direction', 'call_direction', 'type',
            ]) ?: 'inbound'))),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration' => $duration ?? 0,
            'recording_url' => $this->first($payload, [
                'recording_url', 'recording', 'recordingUrl', 'filename', 'audio_url', 'call_recording',
            ]),
            'dtmf_option' => $this->first($payload, [
                'dtmf_option', 'dtmf', 'keypress', 'key_pressed', 'ivr_option', 'option',
            ]),
            'raw_payload' => $payload,
        ];
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) === 11 && str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        return $phone;
    }

    private function first(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return null;
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDuration(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $value, $matches)) {
            $parts = array_map('intval', array_slice($matches, 1));
            if (count($parts) === 2) {
                return ($parts[0] * 60) + $parts[1];
            }

            return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        }

        return null;
    }
}
