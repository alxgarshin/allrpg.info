<?php

declare(strict_types=1);

namespace Ical;

use DateTime;
use DateTimeZone;
use Exception;

trait SimpleICS_Util
{
    public function filter_linelimit($input, $lineLimit = 70)
    {
        // go through each line and make them shorter.
        $output = '';
        $pos = 0;

        while ($pos < mb_strlen($input)) {
            // find the newline
            $newLinepos = mb_strpos($input, "\n", $pos + 1);

            if (!$newLinepos) {
                $newLinepos = mb_strlen($input);
            }
            $line = mb_substr($input, $pos, $newLinepos - $pos);

            if (mb_strlen($line) <= $lineLimit) {
                $output .= $line;
            } else {
                // First line cut-off limit is $lineLimit
                $output .= mb_substr($line, 0, $lineLimit);
                $line = mb_substr($line, $lineLimit);

                // Subsequent line cut-off limit is $lineLimit - 1 due to the leading white space
                $output .= "\n " . mb_substr($line, 0, $lineLimit - 1);

                while (mb_strlen($line) > $lineLimit - 1) {
                    $line = mb_substr($line, $lineLimit - 1);
                    $output .= "\n " . mb_substr($line, 0, $lineLimit - 1);
                }
            }
            $pos = $newLinepos;
        }

        return $output;
    }

    public function filter_calDate($input)
    {
        if (!is_a($input, 'DateTime')) {
            $input = new DateTime($input);
        } else {
            $input = clone $input;
        }
        $input->setTimezone(new DateTimeZone('UTC'));

        return $input->format('Ymd\THis\Z');
    }

    public function filter_serialize($input)
    {
        if (is_object($input)) {
            /* @var $input SimpleICS_Event|SimpleICS */
            return $input->serialize();
        }

        if (is_array($input)) {
            $output = '';
            array_walk($input, function ($item) use (&$output): void {
                $output .= $this->filter_serialize($item);
            });

            return trim($output, "\r\n");
        }

        return $input;
    }

    public function filter_quote($input)
    {
        return quoted_printable_encode($input);
    }

    public function filter_escape($input)
    {
        $input = preg_replace('/([,;])/', '\\\$1', $input);
        $input = str_replace("\n", '\\n', $input);

        return str_replace("\r", '\\r', $input);
    }

    public function render($tpl, $scope)
    {
        while (preg_match("/\{\{([^|}]+)((?:\|([^|}]+))+)?}}/", $tpl, $m)) {
            // $replace = $m[0];
            $varname = $m[1];
            $filters = isset($m[2]) ? explode('|', trim($m[2], '|')) : [];

            $value = $this->fetch_var($scope, $varname);
            $self = &$this;
            array_walk($filters, static function (&$item) use (&$value, $self): void {
                $item = trim($item, "\t\r\n ");

                if (!is_callable([$self, 'filter_' . $item])) {
                    throw new Exception('No such filter: ' . $item);
                }

                $value = call_user_func_array([$self, 'filter_' . $item], [$value]);
            });

            $tpl = str_replace($m[0], $value, $tpl);
        }

        return $tpl;
    }

    public function fetch_var($scope, $var)
    {
        if (mb_strpos($var, '.') !== false) {
            $split = explode('.', $var);
            $var = array_shift($split);
            $rest = implode('.', $split);
            $val = $this->fetch_var($scope, $var);

            return $this->fetch_var($val, $rest);
        }

        if (is_object($scope)) {
            $getterMethod = 'get' . ucfirst($var);

            if (method_exists($scope, $getterMethod)) {
                return $scope->{$getterMethod}();
            }

            return $scope->{$var};
        }

        if (is_array($scope)) {
            return $scope[$var];
        }

        throw new Exception('A strange scope');
    }
}
