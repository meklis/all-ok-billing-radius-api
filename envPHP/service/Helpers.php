<?php


namespace envPHP\service;


class Helpers
{

    static function beautyPhoneNumber($phone)
    {
        $phone = str_replace(['+', ' ', '-', '(', ')'], '', $phone);
        if (strlen($phone) < 9) {
            throw new \InvalidArgumentException("Phone number $phone isn't correct");
        }
        switch (strlen($phone)) {
            case 9:
                $phone = "+380" . $phone;
                break;
            case 10:
                $phone = "+38" . $phone;
                break;
            case 11:
                $phone = "+3" . $phone;
                break;
            case 12:
                $phone = "+" . $phone;
                break;
        }
        return $phone;
    }

    static  function getExtensionFromBase64($base64)
    {
        $splited = explode(',', substr($base64, 5), 2);
        $mime = $splited[0];
        $mime_split_without_base64 = explode(';', $mime, 2);
        $mime_split = explode('/', $mime_split_without_base64[0], 2);
        $extension = 'png';
        if (count($mime_split) == 2) {
            $extension = $mime_split[1];
            if ($extension == 'jpeg') $extension = 'jpg';
        }
        return $extension;
    }

    static  function saveBase64File($base64, $destPath)
    {
        $splited = explode(',', substr($base64, 5), 2);
        $data = $splited[1];
        return @file_put_contents($destPath, base64_decode($data));
    }
}