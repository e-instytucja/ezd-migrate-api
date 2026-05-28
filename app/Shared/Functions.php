<?php
namespace App\Shared;

use DateTime;

class Functions {
    /**
     * Konwertuje datę do formatu ISO 8601 (np. xsd:dateTime).
     * format: [-]CCYY-MM-DDThh:mm:ss[Z|(+|-)hh:mm]
     *
     * @param string|DateTime $dateTime DateTime object, timestamp string or formatted date string.
     * @param bool            $isTimeStamp
     *
     * @return bool|string
     */
    public static function convertToISO8601($dateTime, $isTimeStamp = false)
    {
        if ($dateTime instanceof DateTime) {
            $dateTimeObj = $dateTime;
        } else {
            if ($isTimeStamp) {
                if (ctype_digit($dateTime) && strtotime(date('Y-m-d H:i:s', $dateTime)) === (int)$dateTime) {
                    $dateTimeObj = new DateTime();
                    $dateTimeObj->setTimestamp($dateTime);
                } else {
                    return $dateTime;
                }
            } else {
                $dateTimeObj = new DateTime($dateTime);
            }
        }

        $filteredDateTime = date('c', $dateTimeObj->getTimestamp());

        return $filteredDateTime;
    }

    public static function normalizeText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        return html_entity_decode(
            strip_tags($text),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }



    public static function startTimer(): float
    {
        return microtime(true);
    }

    public static function elapsedMs(float $startedAt): string
    {
        $ms = (microtime(true) - $startedAt) * 1000;

//        if ($ms < 1000) {
//            return round($ms) . ' ms';
//        }

        $seconds = $ms / 1000;

        if ($seconds < 60) {
            return round($seconds, 6) . ' s';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds % 60);

        return sprintf('%d min %d s', $minutes, $remainingSeconds);
    }

    /**
     * dodanie do datey liczby dni
     *
     * @param string  $date
     * @param integer $days
     *
     * @return string
     */
    public static function extendDateByDays($date, $days)
    {
        //wyrażenie regularne musi być, bo jezeli np. nie wypełni się w formularzu pola 'data' (jezeli pole nie jest obowiązkowe)
        //to $date ustawia się DBTimeStamp(time()) - jako string w pojedynczych łapach : string(21) "'2011-04-01 12:16:47'"
        preg_match("/[0-9]{4}-[0-9]{2}-[0-9]{2}/", $date, $regs);
        $date = mktime(
            23,
            59,
            0,
            date('m', strtotime($regs[0])),
            date('d', strtotime($regs[0])) + $days,
            date('Y', strtotime($regs[0]))
        );
        $return = date('Y-m-d', $date);

        return $return;
    }
}