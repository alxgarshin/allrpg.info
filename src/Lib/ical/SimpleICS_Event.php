<?php

declare(strict_types=1);

namespace Ical;

class SimpleICS_Event
{
    use SimpleICS_Util;

    public string $uniqueId = '';
    public string $startDate = '';
    public string $endDate = '';
    public string $dateStamp = '';
    public string $location = '';
    public string $description = '';
    public string $uri = '';
    public string $summary = '';

    public static string $Template = <<<EOT
BEGIN:VEVENT
UID:{{uniqueId}}
DTSTART:{{startDate|calDate}}
DTSTAMP:{{dateStamp|calDate}}
DTEND:{{endDate|calDate}}
LOCATION:{{location|escape}}
DESCRIPTION:{{description|escape}}
URL;VALUE=URI:{{uri|escape}}
SUMMARY:{{summary|escape}}
END:VEVENT

EOT;

    public function __construct()
    {
        $this->uniqueId = uniqid();
    }

    public function serialize()
    {
        return $this->render(self::$Template, $this);
    }
}
