<?php

namespace App\Model;

use Nette;

/**
 * Cart management.
 */
class CartManager extends BaseManager
{
    // use Nette\SmartObject;
    
    public function getCartIDByUP($userid,$partnerid) {
        $cid = $this->database->query(
                "SELECT id FROM cart 
                WHERE user_id = ? AND partner_id = ?", 
                $userid,$partnerid)->fetch();
        
        if (!$cid) {
            $this->database->query(
                    "INSERT INTO cart (user_id,partner_id) VALUES (?,?)",
                    $userid,$partnerid              
            );
            return $this->database->getInsertId(); // vrátí auto-increment vloženého záznamu
            
        } else {
            return $cid['id'];
        }
          
    }

    public function getCartItem($cartid,$zbozi_id){
        return $this->database->query(
                "SELECT cz.*,z.nazev,z.zkratka,z.kod,z.ean,z.baleni,z.paleta,z.jednotka,z.dph,z.hmotnost,z.objemova_jednotka,z.min_obj,
                cr.cena_bez_dph AS cena_zakladni_bez_dph,car.prodejni_cena_bez_dph AS cena_akcni_bez_dph,
                DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AS akcni_od,ca.platnost_do AS akcni_do,
                IF ((NOW() BETWEEN DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AND ca.platnost_do) 
                AND (car.prodejni_cena_bez_dph IS NOT NULL), car.prodejni_cena_bez_dph, cr.cena_bez_dph) AS cena_zbozi_bez_dph,
                kz.kategorie_id, k.nazev AS kategorie_nazev
                FROM cart_zbozi cz
                JOIN cart c ON c.id = cz.cart_id
                JOIN zbozi z ON z.id = cz.zbozi_id
                JOIN partner p ON p.id = c.partner_id
                JOIN ceniky_radky cr ON cr.cenik_id = p.cenik_id AND cr.zbozi_id = cz.zbozi_id AND cr.cena_bez_dph > 0
                LEFT JOIN ceniky_akcni_radky car ON car.cenik_akcni_id = p.cenik_akcni_id AND car.zbozi_id = cz.zbozi_id AND car.prodejni_cena_bez_dph > 0
                LEFT JOIN ceniky_akcni ca ON ca.id = p.cenik_akcni_id
                LEFT JOIN kategorie_zbozi kz ON kz.zbozi_id = cz.zbozi_id 
                LEFT JOIN kategorie k ON k.id = kz.kategorie_id
                WHERE cz.cart_id = ? AND cz.zbozi_id = ?",$cartid,$zbozi_id);        
    }
    
    public function getAllItems($cartid){
        return $this->database->query(
                "SELECT cz.*,z.nazev,z.zkratka,z.kod,z.ean,z.baleni,z.paleta,z.jednotka,z.dph,z.hmotnost,z.objemova_jednotka,z.min_obj,
                cr.cena_bez_dph AS cena_zakladni_bez_dph,car.prodejni_cena_bez_dph AS cena_akcni_bez_dph,
                DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AS akcni_od,ca.platnost_do AS akcni_do,
                IF ((NOW() BETWEEN DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AND ca.platnost_do) 
                AND (car.prodejni_cena_bez_dph IS NOT NULL), car.prodejni_cena_bez_dph, cr.cena_bez_dph) AS cena_zbozi_bez_dph,
                kz.kategorie_id, k.nazev AS kategorie_nazev
                FROM cart_zbozi cz
                JOIN cart c ON c.id = cz.cart_id
                JOIN zbozi z ON z.id = cz.zbozi_id
                JOIN partner p ON p.id = c.partner_id
                JOIN ceniky_radky cr ON cr.cenik_id = p.cenik_id AND cr.zbozi_id = cz.zbozi_id AND cr.cena_bez_dph > 0
                LEFT JOIN ceniky_akcni_radky car ON car.cenik_akcni_id = p.cenik_akcni_id AND car.zbozi_id = cz.zbozi_id AND car.prodejni_cena_bez_dph > 0
                LEFT JOIN ceniky_akcni ca ON ca.id = p.cenik_akcni_id
                LEFT JOIN kategorie_zbozi kz ON kz.zbozi_id = cz.zbozi_id 
                LEFT JOIN kategorie k ON k.id = kz.kategorie_id
                WHERE cz.cart_id = ? AND cz.pocet <> 0 ORDER BY kategorie_id,poradi_id",$cartid);        
    }
    
    public function getTotalPrice($cartid){
        return $this->database->query(
                "SELECT SUM(
                    IF ((NOW() BETWEEN DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) 
                    AND ca.platnost_do) AND (car.prodejni_cena_bez_dph IS NOT NULL), 
                    car.prodejni_cena_bez_dph, cr.cena_bez_dph) * pocet
                ) AS total
                FROM cart_zbozi cz
                JOIN cart c ON c.id = cz.cart_id
                JOIN zbozi z ON z.id = cz.zbozi_id
                JOIN partner p ON p.id = c.partner_id
                JOIN ceniky_radky cr ON cr.cenik_id = p.cenik_id AND cr.zbozi_id = cz.zbozi_id AND cr.cena_bez_dph > 0
                LEFT JOIN ceniky_akcni_radky car ON car.cenik_akcni_id = p.cenik_akcni_id AND car.zbozi_id = cz.zbozi_id AND car.prodejni_cena_bez_dph > 0
                LEFT JOIN ceniky_akcni ca ON ca.id = p.cenik_akcni_id
                WHERE cz.cart_id = ?", $cartid)->fetch()->total;
    }
    
    /**
     * 
     * @param int $cartid
     * @param string $zprava
     * @param DateTime $datum_zavozu
     * @return bool|int
     */
    public function makeOrder($cartid,$zprava,$datum_zavozu){
        try {
            $this->database->query(
                    "INSERT INTO obj (user_id,partner_id,zprava,sps_cislo,datum,zmena,datum_dodani) 
                    SELECT user_id,partner_id,?,NULL,NOW(),NOW(),? FROM cart c
                    WHERE c.id = ?", $zprava, $datum_zavozu, $cartid
            );
            $obj_id = $this->database->getInsertId(); // vrátí auto-increment vloženého záznamu
            $this->database->query(
                    "INSERT INTO obj_zbozi (obj_id,zbozi_id,pocet,cena_bez_dph)
                    SELECT ?,cz.zbozi_id,cz.pocet,
                    IF ((NOW() BETWEEN DATE_ADD(ca.platnost_od, INTERVAL -3 DAY) AND ca.platnost_do)
                    AND (car.prodejni_cena_bez_dph IS NOT NULL), car.prodejni_cena_bez_dph, cr.cena_bez_dph)
                    FROM cart_zbozi cz
                    JOIN cart c ON c.id = cz.cart_id
                    JOIN zbozi z ON z.id = cz.zbozi_id
                    JOIN partner p ON p.id = c.partner_id
                    JOIN ceniky_radky cr ON cr.cenik_id = p.cenik_id AND cr.zbozi_id = cz.zbozi_id AND cr.cena_bez_dph > 0
                    LEFT JOIN ceniky_akcni_radky car ON car.cenik_akcni_id = p.cenik_akcni_id AND car.zbozi_id = cz.zbozi_id AND car.prodejni_cena_bez_dph > 0
                    LEFT JOIN ceniky_akcni ca ON ca.id = p.cenik_akcni_id
                    WHERE cz.cart_id = ? AND cz.pocet > 0",$obj_id,$cartid
            );                            
            return $obj_id;
        } catch (Exception $ex) {
            return false;
        }
    }

    public function itemsInCart($cartid){
        return $this->database->query("SELECT count(zbozi_id) AS pocet FROM cart_zbozi
                              WHERE cart_id =?",$cartid
                )->fetch();
    }
    
    public function removeCart($cartid){
        //$this->update("cart",array("cart_id"=>$cart_id),array("paid"=>"true"));
        $this->database->query("DELETE FROM cart WHERE id = ?",$cartid);
        $this->database->query("DELETE FROM cart_zbozi WHERE cart_id = ?",$cartid);
    }

    public function removeCartItems($cartid){
        $this->database->query("DELETE FROM cart_zbozi WHERE cart_id = ?",$cartid);
    }

    public function addItem($userid,$partnerid,$zbozid,$pocet) {
    
        $cid = $this->getCartIDByUP($userid,$partnerid);
        
        if (!$cid) {
            return false;
        }        
        $ins_pocet = $pocet > 0 ? $pocet : 0;
        $this->database->query(
                "INSERT INTO cart_zbozi (cart_id,zbozi_id,pocet) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE pocet = IF(pocet+? < 0,0,pocet+?)",
                $cid,$zbozid,$ins_pocet,$pocet,$pocet
        );
        return true;
    }

    public function setItem($userid,$partnerid,$zbozid,$pocet) {
    
        $cid = $this->getCartIDByUP($userid,$partnerid);        
        if (!$cid) {
            return false;
        }        
        $ins_pocet = $pocet > 0 ? $pocet : 0;
        $this->database->query(
                "INSERT INTO cart_zbozi (cart_id,zbozi_id,pocet) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE pocet = ?",
                $cid,$zbozid,$ins_pocet,$ins_pocet
        );
        return true;
    }

    public function addItemsFromOrder($userid,$partnerid,$objid) {
    
        $cid = $this->getCartIDByUP($userid,$partnerid);        
        if (!$cid) {
            return false;
        }   
        
        return $this->database->query(
                "INSERT INTO cart_zbozi (cart_id,zbozi_id,pocet) 
                 SELECT * FROM (
                   SELECT ?,zbozi_id,pocet AS n_pocet 
                   FROM obj_zbozi oz WHERE obj_id = ?
                 ) AS dt
                 ON DUPLICATE KEY UPDATE pocet = pocet + dt.n_pocet",
                 $cid,$objid
        )->getRowCount();
        
        // POZOR - insert or update, pokud položky již jsou v tabulce, mi vrací 
        // špatné číslo - každý update, kde položka již je vložena, se počítá 2x
        // Proto číslo getRowCount() není příliš použitelné...
    }
    
    public function addFavItemsFromOrder($userid,$partnerid,$objid) {
    
        $cid = $this->getCartIDByUP($userid,$partnerid);        
        if (!$cid) {
            return false;
        }   
        
        return $this->database->query(
                "INSERT INTO cart_zbozi (cart_id,zbozi_id,pocet) 
                 SELECT * FROM (
                   SELECT ?,oz.zbozi_id,pocet AS n_pocet 
                   FROM obj_zbozi oz, partner_zbozi pz 
                   WHERE obj_id = ? AND pz.partner_id = ? AND oz.zbozi_id = pz.zbozi_id
                 ) AS dt
                 ON DUPLICATE KEY UPDATE pocet = pocet + dt.n_pocet",
                 $cid,$objid,$partnerid
        )->getRowCount();
        //return true;
        // POZOR - insert or update, pokud položky již jsou v tabulce, mi vrací 
        // špatné číslo - každý update, kde položka již je vložena, se počítá 2x
        // Proto číslo getRowCount() není příliš použitelné...
    }
        
}
