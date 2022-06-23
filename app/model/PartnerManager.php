<?php

namespace App\Model;

use Nette;
use Nette\Utils\DateTime;

/**
 * Partner management.
 */
class PartnerManager extends BaseManager
{
    use Nette\SmartObject;
    
    const
        TABLE_NAME = 'partner',
        COLUMN_ID = 'id',
        COLUMN_NAZEV = 'nazev',
        COLUMN_NAZEV2 = 'nazev2',
        COLUMN_ULICE = 'ulice',
        COLUMN_MESTO = 'mesto',
        COLUMN_PSC = 'psc',
        COLUMN_IC = 'ic',
        COLUMN_DIC = 'dic',
        COLUMN_NAZEV_FAKTURA = 'nazev_faktura',
        COLUMN_NAZEV2_FAKTURA = 'nazev2_faktura',
        COLUMN_ULICE_FAKTURA = 'ulice_faktura',
        COLUMN_MESTO_FAKTURA = 'mesto_faktura',
        COLUMN_PSC_FAKTURA = 'psc_faktura',
        COLUMN_LATITUDE = 'latitude',
        COLUMN_LONGITUDE = 'longitude',
        COLUMN_VELKY_PARTNER = 'velky_partner',
        COLUMN_ZMENA = 'zmena',
    
        TABLE_NAME_USER_PARTNER = 'user_partner',
        COLUMN_USER_ID = 'user_id',
        COLUMN_PARTNER_ID = 'partner_id';    

    /**
     * Vrátí seznam partnerů v databázi daného uživatele.
     * @param int $userID ID uživatele
     * @return Resultset
     */
    public function getPartners($userID = NULL)
    {
        if (is_null($userID)) {
            return $this->database->table(self::TABLE_NAME)->order(self::COLUMN_NAZEV . ' ASC');
        } else {
            return $this->database->query(
                'SELECT '.self::TABLE_NAME.'.* FROM '.self::TABLE_NAME_USER_PARTNER.','.self::TABLE_NAME.
                ' WHERE '.self::TABLE_NAME_USER_PARTNER.'.'.self::COLUMN_USER_ID.' = ? AND '.
                self::TABLE_NAME_USER_PARTNER.'.'.self::COLUMN_PARTNER_ID.' = '.
                self::TABLE_NAME.'.'.self::COLUMN_ID. 
                ' ORDER BY '.self::COLUMN_NAZEV . ' ASC',$userID);
        }
    }
    
    /**
     * Vrátí partnera konkrétního uživatele z databáze podle jeho ID.
     * Zároveň kontroluje, jestli uživatel může s partnerem pracovat.
     * @param int $userID ID uživatele
     * @param int $pID ID partnera
     * @return bool|mixed|IRow
     */
    public function getUserPartner($userID, $pID)
    {
        /* funguje take
        return $this->database->query(
            'SELECT '.self::TABLE_NAME.'.* FROM '.self::TABLE_NAME_USER_SP.','.self::TABLE_NAME.
            ' WHERE '.self::TABLE_NAME_USER_SP.'.'.self::COLUMN_USER_ID.' = ? AND '.
            self::TABLE_NAME_USER_SP.'.'.self::COLUMN_SP_ID.' = ? AND '.
            self::TABLE_NAME_USER_SP.'.'.self::COLUMN_SP_ID.' = '.
            self::TABLE_NAME.'.'.self::COLUMN_ID, $userID, $omID)
                ->fetch();
         *
         */
        return $this->database->fetch(
            'SELECT '.self::TABLE_NAME.'.* FROM '.self::TABLE_NAME_USER_PARTNER.','.self::TABLE_NAME.
            ' WHERE '.self::TABLE_NAME_USER_PARTNER.'.'.self::COLUMN_USER_ID.' = ? AND '.
            self::TABLE_NAME_USER_PARTNER.'.'.self::COLUMN_PARTNER_ID.' = ? AND '.
            self::TABLE_NAME_USER_PARTNER.'.'.self::COLUMN_PARTNER_ID.' = '.
            self::TABLE_NAME.'.'.self::COLUMN_ID, $userID, $pID);
    }
    
    /**
     * Vrátí partnera z databáze podle jeho ID.
     * Nekontroluje, jestli přihlášený uživatel může s partnerem pracovat.
     * @param string $id ID partnera
     * @return bool|mixed|IRow
     */
    public function getPartner($id)
    {
        return $this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID, $id)->fetch();
    }
    
    /**
     * Vrátí zboží partnera s aktuálními cenami a označením množství, které 
     * je již v košíku konkrétního uživatele.
     * U každého zboží vrací také kategorii.
     * @param int $userID ID uživatele
     * @param int $pID ID partnera
     * @return Resultset
     */
    public function getUserPartnerZbozi($userID, $pID)
    {
        return $this->database->query(
            "SELECT cr.zbozi_id,z.nazev,z.zkratka,z.kod,z.ean,z.baleni,z.paleta,
            z.jednotka,z.dph,z.hmotnost,z.objemova_jednotka,z.min_obj,
            cr.cena_bez_dph AS cena_zakladni_bez_dph,car.prodejni_cena_bez_dph AS cena_akcni_bez_dph,
            DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AS akcni_od,ca.platnost_do AS akcni_do,
            IF ((NOW() BETWEEN DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AND ca.platnost_do) 
            AND (car.prodejni_cena_bez_dph IS NOT NULL), car.prodejni_cena_bez_dph, cr.cena_bez_dph) AS cena_zbozi_bez_dph,
            c.id AS cart_id, cz.pocet, kz.kategorie_id, k.nazev AS kategorie_nazev
            FROM partner p
            JOIN ceniky_radky cr ON cr.cenik_id = p.cenik_id AND cr.cena_bez_dph > 0
            JOIN zbozi z ON z.id = cr.zbozi_id
            LEFT JOIN cart c ON c.user_id = ? AND c.partner_id = p.id
            LEFT JOIN cart_zbozi cz ON cz.zbozi_id = cr.zbozi_id AND c.id = cz.cart_id
            LEFT JOIN ceniky_akcni_radky car ON car.cenik_akcni_id = p.cenik_akcni_id 
            AND car.zbozi_id = cz.zbozi_id AND car.prodejni_cena_bez_dph > 0
            LEFT JOIN ceniky_akcni ca ON ca.id = p.cenik_akcni_id
            LEFT JOIN kategorie_zbozi kz ON kz.zbozi_id = cr.zbozi_id 
            LEFT JOIN kategorie k ON k.id = kz.kategorie_id
            WHERE p.id = ? ORDER BY kategorie_id,poradi_id", $userID, $pID
                );
    }    

    /**
     * Pro AJAX - Vrátí konkrétní zboží partnera s aktuálními cenami a označením 
     * množství, které je již v košíku konkrétního uživatele.
     * U zboží vrací také kategorii.
     * @param int $userID
     * @param int $pID
     * @param int $zboziID
     * @return Resultset
     * 
     */
    public function getUserPartnerZboziID($userID, $pID, $zboziID)
    {
        return $this->database->query(
            "SELECT cr.zbozi_id,z.nazev,z.zkratka,z.kod,z.ean,z.baleni,z.paleta,
            z.jednotka,z.dph,z.hmotnost,z.objemova_jednotka,z.min_obj,
            cr.cena_bez_dph AS cena_zakladni_bez_dph,car.prodejni_cena_bez_dph AS cena_akcni_bez_dph,
            DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AS akcni_od,ca.platnost_do AS akcni_do,
            IF ((NOW() BETWEEN DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AND ca.platnost_do) 
            AND (car.prodejni_cena_bez_dph IS NOT NULL), car.prodejni_cena_bez_dph, cr.cena_bez_dph) AS cena_zbozi_bez_dph,
            c.id AS cart_id, cz.pocet, kz.kategorie_id, k.nazev AS kategorie_nazev
            FROM partner p
            JOIN ceniky_radky cr ON cr.cenik_id = p.cenik_id AND cr.cena_bez_dph > 0
            JOIN zbozi z ON z.id = cr.zbozi_id
            LEFT JOIN cart c ON c.user_id = ? AND c.partner_id = p.id
            LEFT JOIN cart_zbozi cz ON cz.zbozi_id = cr.zbozi_id AND c.id = cz.cart_id
            LEFT JOIN ceniky_akcni_radky car ON car.cenik_akcni_id = p.cenik_akcni_id 
            AND car.zbozi_id = cz.zbozi_id AND car.prodejni_cena_bez_dph > 0
            LEFT JOIN ceniky_akcni ca ON ca.id = p.cenik_akcni_id
            LEFT JOIN kategorie_zbozi kz ON kz.zbozi_id = cr.zbozi_id 
            LEFT JOIN kategorie k ON k.id = kz.kategorie_id
            WHERE p.id = ? AND cr.zbozi_id = ?", $userID, $pID, $zboziID
                );
    }    

    /**
     * Zobrazí základní seznam zboží partnera dle kategorií pro jeho
     * výběr/odebrání do/z oblíbených položek - to je pak řešeno ajaxem.
     * U každého zboží vrací také kategorii.
     * @param int $pID ID partnera
     * @return Resultset
     */
    public function getPartnerZboziFav($pID)
    {
        return $this->database->query(
            "SELECT cr.zbozi_id,z.nazev,z.zkratka,z.kod,z.ean,z.baleni,z.paleta,
            z.jednotka,z.dph,z.hmotnost,z.objemova_jednotka,z.min_obj,
            kz.kategorie_id, k.nazev AS kategorie_nazev,
            IF (pz.zbozi_id IS NULL,'N','A') AS oblibene
            FROM partner p
            JOIN ceniky_radky cr ON cr.cenik_id = p.cenik_id AND cr.cena_bez_dph > 0
            JOIN zbozi z ON z.id = cr.zbozi_id
            LEFT JOIN partner_zbozi pz ON pz.partner_id = p.id AND pz.zbozi_id = cr.zbozi_id
            LEFT JOIN kategorie_zbozi kz ON kz.zbozi_id = cr.zbozi_id 
            LEFT JOIN kategorie k ON k.id = kz.kategorie_id
            WHERE p.id = ? ORDER BY kategorie_id,poradi_id", $pID
                );
    }    

    /**
     * Pro AJAX - Vrátí konkrétní zboží partnera.
     * U zboží vrací také kategorii.
     * @param int $pID ID partnera
     * @param int $zboziID
     * @return Resultset
     */
    public function getPartnerZboziFavID($pID,$zboziID)
    {
        return $this->database->query(
            "SELECT cr.zbozi_id,z.nazev,z.zkratka,z.kod,z.ean,z.baleni,z.paleta,
            z.jednotka,z.dph,z.hmotnost,z.objemova_jednotka,z.min_obj,
            kz.kategorie_id, k.nazev AS kategorie_nazev,
            IF (pz.zbozi_id IS NULL,'N','A') AS oblibene
            FROM partner p
            JOIN ceniky_radky cr ON cr.cenik_id = p.cenik_id AND cr.cena_bez_dph > 0
            JOIN zbozi z ON z.id = cr.zbozi_id
            LEFT JOIN partner_zbozi pz ON pz.partner_id = p.id AND pz.zbozi_id = cr.zbozi_id
            LEFT JOIN kategorie_zbozi kz ON kz.zbozi_id = cr.zbozi_id 
            LEFT JOIN kategorie k ON k.id = kz.kategorie_id
            WHERE p.id = ? AND cr.zbozi_id = ?", $pID, $zboziID
                );
    }    
    
    /**
     * Vrátí oblíbené zboží partnera s aktuálními cenami a označením množství, 
     * které je již v košíku konkrétního uživatele. Pokud něco v košíku již je, 
     * ale není to v oblíbených, nezobrazí se.
     * U každého zboží vrací také kategorii.
     * @param int $userID ID uživatele
     * @param int $pID ID partnera 
     * @return Resultset
     */ 
    public function getUserPartnerZboziOblibene($userID, $pID)
    {
        return $this->database->query(                
            "SELECT cr.zbozi_id,z.nazev,z.zkratka,z.kod,z.ean,z.baleni,z.paleta,
            z.jednotka,z.dph,z.hmotnost,z.objemova_jednotka,z.min_obj,
            cr.cena_bez_dph AS cena_zakladni_bez_dph,car.prodejni_cena_bez_dph AS cena_akcni_bez_dph,
            DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AS akcni_od,ca.platnost_do AS akcni_do,
            IF ((NOW() BETWEEN DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AND ca.platnost_do) 
            AND (car.prodejni_cena_bez_dph IS NOT NULL), car.prodejni_cena_bez_dph, cr.cena_bez_dph) AS cena_zbozi_bez_dph,
            c.id AS cart_id, cz.pocet, kz.kategorie_id, k.nazev AS kategorie_nazev
            FROM partner p
            JOIN ceniky_radky cr ON cr.cenik_id = p.cenik_id AND cr.cena_bez_dph > 0
            JOIN zbozi z ON z.id = cr.zbozi_id
            JOIN partner_zbozi pz ON pz.partner_id = p.id AND pz.zbozi_id = cr.zbozi_id
            LEFT JOIN cart c ON c.user_id = ? AND c.partner_id = p.id
            LEFT JOIN cart_zbozi cz ON cz.zbozi_id = cr.zbozi_id AND c.id = cz.cart_id
            LEFT JOIN ceniky_akcni_radky car ON car.cenik_akcni_id = p.cenik_akcni_id 
            AND car.zbozi_id = cz.zbozi_id AND car.prodejni_cena_bez_dph > 0
            LEFT JOIN ceniky_akcni ca ON ca.id = p.cenik_akcni_id
            LEFT JOIN kategorie_zbozi kz ON kz.zbozi_id = cr.zbozi_id 
            LEFT JOIN kategorie k ON k.id = kz.kategorie_id
            WHERE p.id = ? ORDER BY kategorie_id,poradi_id", $userID, $pID
                );
    }    

    /**
     * Vrátí číslo poslední objednávky partnera. 
     * Vrací také jméno uživatele, který ji vytvořil.
     * @param int $partnerid ID partnera
     * @return type
     */
    public function getLastOrderID($partnerid) {        
        return $this->database->query(
            "SELECT obj.*, u.jmeno, u.kontakt FROM obj 
            LEFT JOIN `user` u ON u.id = obj.user_id
            WHERE partner_id = ?
            ORDER BY datum DESC LIMIT 1",$partnerid
                )->fetch();        
    }
    
    /**
     * Vrátí zboží z objednávky partnera - kontroluje, jestli má uživatel
     * partnera povoleného a jestli je číslo objednávky správného partnera.
     * U každého zboží vrací také kategorii.
     * @param int $userID ID uživatele
     * @param int $pID ID partnera 
     * @param int $objID ID objednávky
     * @return Resultset
     */ 
    public function getUserPartnerZboziObj($userID, $pID, $objID)
    {
        return $this->database->query(                
            "SELECT oz.zbozi_id, oz.pocet, 
            z.nazev,z.zkratka,z.kod,z.ean,z.baleni,z.paleta,z.jednotka,z.dph,
            z.hmotnost,z.objemova_jednotka,z.min_obj,
            kz.kategorie_id, k.nazev AS kategorie_nazev
            FROM obj_zbozi oz
            JOIN obj o ON o.id = oz.obj_id
            JOIN zbozi z ON z.id = oz.zbozi_id
            JOIN user_partner up ON up.user_id = ?
              AND up.partner_id = o.partner_id
            LEFT JOIN kategorie_zbozi kz ON kz.zbozi_id = oz.zbozi_id 
            LEFT JOIN kategorie k ON k.id = kz.kategorie_id
            WHERE o.partner_id = ? AND obj_id = ?
            ORDER BY kategorie_id,poradi_id", $userID, $pID, $objID
                );
    }    
    
    /**
     * @param int $pID partner_id
     * @param DateTime $date
     * @return false|array(trasaDate => DateTime datum, trasaID => int trasa_id)
     */
    public function najdiNejblizsiDatumTrasy($pID, DateTime $date) 
    {
        $datum = clone $date;
        $res = $this->database->query(
                "SELECT * FROM trasa WHERE trasa.id IN (SELECT trasa_id FROM trasa_partner
                 WHERE partner_id = ? AND den <> 0)", $pID
                )->fetch();
        // pokud nebyla nalezena zadna trasa (nemelo by se stavat), vratim false
        if (!$res) {
            return false;
        }        
        // jinak iterace pres 14 dni - v nich by mela byt nalezena trasa  
        $prirustek = 1; // budeme hledat dopredu  
        //$arrD = [];
        for ($i = 0; $i < 14; $i++) {
            if ($i > 0) { $datum->modify("{$prirustek} day"); }
            $ADayOfWeek = $datum->format('N');
            if ($this->isOddWeek($datum)) {
                switch ($ADayOfWeek) {
                    case 1: $mask = bindec('10000000000000'); break;
                    case 2: $mask = bindec('01000000000000'); break;
                    case 3: $mask = bindec('00100000000000'); break;
                    case 4: $mask = bindec('00010000000000'); break;
                    case 5: $mask = bindec('00001000000000'); break;
                    case 6: $mask = bindec('00000100000000'); break;
                    case 7: $mask = bindec('00000010000000'); break;
                }
            } else {
                switch ($ADayOfWeek) {
                    case 1: $mask = bindec('00000001000000'); break;
                    case 2: $mask = bindec('00000000100000'); break;
                    case 3: $mask = bindec('00000000010000'); break;
                    case 4: $mask = bindec('00000000001000'); break;
                    case 5: $mask = bindec('00000000000100'); break;
                    case 6: $mask = bindec('00000000000010'); break;
                    case 7: $mask = bindec('00000000000001'); break;
                }
            }
            $res = $this->database->query(
                    "SELECT * FROM trasa WHERE trasa.id IN (SELECT trasa_id FROM trasa_partner
                     WHERE partner_id = ? AND den & ? > 0)", $pID,$mask
                    )->fetch();            
            // pokud nebyla nalezena zadna trasa (nemelo by se stavat), vratim false
            if ($res) {
                $arr = ['trasaDate' => $datum, 'trasaID' => $res['id']];
                return $arr; // ukoncime
            }
            //$arrD[] = ['datum' => $datum->format('Y-m-d'),'day' => $ADayOfWeek, 'odd' => $this->isOddWeek($datum), 'mask' => $mask, 'trasa_id' => $res['id']];
        }
        //return $arrD; // debugging
        return false;
    }    
    
    public function vratZavoz($pID)
    {    
        // vypocet mozneho datumu zavozu
        //$DT = DateTime::createFromFormat('Y-m-d H:i:s', '2022-04-13 08:00:00');
        $DT = new DateTime();
        $DT2 = $this->vratPrvniDenZavozu($DT);
        $arr = $this->najdiNejblizsiDatumTrasy($pID, $DT2);
        if (!$arr) {
            $arr = ['trasaDate' => null, 'trasaID' => null, 
                'zavozDate' => null, 'zavozInfo' => '' 
                ];
        } else {
            // datum trasy byl nalezen, zkontroluju, jestli datum a trasa nahodou 
            // neni v seznamu datumu, ktere se nahrazuji
            $res = $this->database->query(
                    "SELECT * FROM trasa_nahrady WHERE datum = ? AND trasa_id = ?", 
                    $arr['trasaDate']->format('Y-m-d'),$arr['trasaID']
                    )->fetch();
            // pokud nebyla nalezen zaznam pro tuto trasu, zkusim jeste hledat 
            // obecnou nahradu (trasa_id = NULL)
            if (!$res) {
                $res = $this->database->query(
                        "SELECT * FROM trasa_nahrady WHERE datum = ? AND trasa_id IS NULL", 
                        $arr['trasaDate']->format('Y-m-d')
                        )->fetch();
            }
            // byl nalezena nahrada
            if ($res) {
                $arr['zavozDate'] = $res['datum_nahrada'];
                $arr['zavozInfo'] = $res['poznamka'];
            } else {
                $arr['zavozDate'] = $arr['trasaDate'];
                $arr['zavozInfo'] = '';
            }            
        }
        $arr['startDate'] = $DT;
        $arr['prvniDate'] = $DT2; 
        
        return $arr;
    }
    
    public function addFavItem($partnerid,$zboziid) {    
        return $this->database->query(
                "INSERT IGNORE INTO partner_zbozi (partner_id, zbozi_id) 
                 SELECT ?,id FROM zbozi WHERE id = ?",
                 $partnerid,$zboziid
        )->getRowCount();
    }

    public function deleteFavItem($partnerid,$zboziid) {    
        return $this->database->query(
                "DELETE FROM partner_zbozi WHERE partner_id = ? AND zbozi_id = ?",
                 $partnerid,$zboziid
        )->getRowCount();
    }
           
}
