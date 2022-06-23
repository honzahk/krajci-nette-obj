<?php

namespace App\Model;

use Nette;
use Nette\Database\Explorer;
use Nette\Utils\DateTime;

/**
 * Základní třída modelu pro všechny modely aplikace.
 * Předává přístup k práci s databází a definuje některé základní funkce.
 * @package App\Model
 */
abstract class BaseManager
{

    use Nette\SmartObject;

    /** @var Explorer Instance třídy pro práci s databází. */
    protected $database;

    /**
     * Konstruktor s injektovanou třídou pro práci s databází.
     * @param Explorer $database automaticky injektovaná třída pro práci s databází
     */
    public function __construct(Explorer $database)
    {
            $this->database = $database;
    }

    public function isOddWeek(DateTime $datum) {
        return ($datum->format('W')%2 > 0);
    }

    public function getEasterSundayDateTime($year) {
        $base = new DateTime("$year-03-21");
        $days = easter_days($year);
        return $base->add(new \DateInterval("P{$days}D"));
    }

    public function isSameDate(DateTime $date1, DateTime $date2) {    
    //    $date1YMD = $date1->format('Ymd');
    //    $date2YMD = $date2->format('Ymd');
        return ($date1->format('Ymd') == $date2->format('Ymd'));
    }

    public function isDateEasterFridayOrMonday(DateTime $date) {    
        $year = $date->format('Y');
        $easterSunday = $this->getEasterSundayDateTime($year);    
        $easterFriday = clone $easterSunday;
        $easterFriday->sub(new \DateInterval("P2D")); 
        //echo "friday: ".$easterFriday->format('Y-m-d H:i:s').'<br>';    
        if ($this->isSameDate($date,$easterFriday)) {
            return true;
        } else {
            $easterMonday = clone $easterSunday;
            $easterMonday->add(new \DateInterval("P1D")); 
            //echo "monday: ".$easterMonday->format('Y-m-d H:i:s').'<br>';    
            if ($this->isSameDate($date,$easterMonday)) {
                return true;
            } else {
                return false;
            }
        }    
    }
    
    /**
     * Funkce od aktualniho data a casu vybere prvni mozny den zavozu - bez ohledu
     * na to, jestli je tento den pracovni ci svatek (vikend, statni svatek).
     * Pokud je na vstupu pracovni den, pak se rozhoduje dle casu - cas pred devatou
     * znamena, ze bude posunovat o 1 pracovni den (PO 8:00 posun na ST), cas po devate
     * pak posun o dva pracovni dny (PO 9:00 posun na CT). Pokud je svatek, pak vzdy
     * posun o dva pracovni dny.
     * @param type $date DateTime
     * @return DateTime
     */
    public function vratPrvniDenZavozu9hodin(DateTime $date) 
    {
        $resD = clone $date;

        $workingDays = [1, 2, 3, 4, 5]; # date format = N (1 = Monday, ...)
        $holidayDays = ['*-01-01','*-05-01','*-05-08','*-07-05','*-07-06','*-09-28','*-10-28','*-11-17','*-12-24','*-12-25','*-12-26'];

        if (in_array($resD->format('N'), $workingDays)) {
            // pracovni den, overime statni svatek
            if ((in_array($resD->format('*-m-d'), $holidayDays)) || $this->isDateEasterFridayOrMonday($resD)) {
                // je statni svatek, posunujeme o dva pracovni dny
                $wdays = 2;
            } elseif ($resD->format('G')<9) {
                // neni statni svatek, je pred devatou, posunujeme o jeden prac.den
                $wdays = 1;
            } else {
                // neni statni svatek, je po devate, posunujeme o dva prac.dny
                $wdays = 2;                
            }                      
        } else {
            // neni pracovni den, posunujeme o dva pracovni dny
            $wdays = 2;
        }  
        //echo "wdays: ".$wdays."<br>";

        // samotny posledni pocitany den mne nezajima, jestli je ci neni statni svatek,
        // proste vratim posledni pracovni den pred timto dnem zavozu, k nemuz prictu 
        // jeden dalsi den - vyhodnoceni, jestli to je ci neni statni svatek, pripadne
        // posun dle jinych pravidel, necham zase na jinem procesu
        while ($wdays) {
            $resD->modify('+1 day');
            if (!in_array($resD->format('N'), $workingDays)) { 
                continue;               
            }
            if ((in_array($resD->format('*-m-d'), $holidayDays)) || $this->isDateEasterFridayOrMonday($resD)) { 
                continue;             
            }
            //echo "   pracovni den, ponizuji wdays...";
            $wdays--;
        }    
        $resD->modify('+1 day');            
        return $resD;    
    }
    
    /**
     * Funkce od aktualniho data a casu vybere prvni mozny den zavozu - bez ohledu
     * na to, jestli je tento den pracovni ci svatek (vikend, statni svatek).
     * Posun vzdy o dva cele pracovni dny.
     * @param type $date DateTime
     * @return DateTime
     */
    public function vratPrvniDenZavozu(DateTime $date) 
    {
        $resD = clone $date;

        $workingDays = [1, 2, 3, 4, 5]; # date format = N (1 = Monday, ...)
        $holidayDays = ['*-01-01','*-05-01','*-05-08','*-07-05','*-07-06','*-09-28','*-10-28','*-11-17','*-12-24','*-12-25','*-12-26'];

        // posunujeme o dva pracovni dny
        $wdays = 2;
        //echo "wdays: ".$wdays."<br>";

        // samotny posledni pocitany den mne nezajima, jestli je ci neni statni svatek,
        // proste vratim posledni pracovni den pred timto dnem zavozu, k nemuz prictu 
        // jeden dalsi den - vyhodnoceni, jestli to je ci neni statni svatek, pripadne
        // posun dle jinych pravidel, necham zase na jinem procesu
        while ($wdays) {
            $resD->modify('+1 day');
            if (!in_array($resD->format('N'), $workingDays)) { 
                continue;               
            }
            if ((in_array($resD->format('*-m-d'), $holidayDays)) || $this->isDateEasterFridayOrMonday($resD)) { 
                continue;             
            }
            //echo "   pracovni den, ponizuji wdays...";
            $wdays--;
        }    
        $resD->modify('+1 day');            
        return $resD;    
    }
        
}