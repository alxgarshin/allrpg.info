<?php

declare(strict_types=1);

namespace Ical;

use Closure;

class SimpleICS
{
    use SimpleICS_Util;

    public const MIME_TYPE = 'text/calendar; charset=utf-8';

    public array $events = [];
    public string $productString = '-//hacksw/handcal//NONSGML v1.0//EN';

    public static string $Template = <<<EOT
BEGIN:VCALENDAR
VERSION:2.0
PRODID:{{productString}}
METHOD:PUBLISH
CALSCALE:GREGORIAN
{{events|serialize}}
END:VCALENDAR

EOT;

    public function addEvent($eventOrClosure)
    {
        if ($eventOrClosure instanceof Closure) {
            $event = new SimpleICS_Event();
            $eventOrClosure($event);
            $this->events[] = $event;

            return $event;
        }

        return;
    }

    public function serialize()
    {
        return $this->filter_linelimit($this->render(self::$Template, $this));
    }
}
