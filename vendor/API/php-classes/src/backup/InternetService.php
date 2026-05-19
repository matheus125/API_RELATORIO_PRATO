<?php

namespace Hcode\Backup;

class InternetService
{
    public function isConnected(): bool
    {
        $connected = @fsockopen("www.google.com", 80, $errno, $errstr, 3);

        if ($connected) {
            fclose($connected);
            return true;
        }

        return false;
    }
}
 