<?php

namespace App\Model;

use Nette;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;

//use Nette\Security\Passwords;
use Nette\Utils\Validators;
//use Nette\Security\IAuthenticator;
////use Nette\Security\User;
//use Nette\Database\Explorer;

/**
 * User management.
 */
class UserManager extends BaseManager implements Nette\Security\Authenticator, Nette\Security\IdentityHandler
{
    use Nette\SmartObject;
    
    private $passwords;

    const
        TABLE_NAME = 'user',
        COLUMN_ID = 'id',
        COLUMN_LOGIN = 'login',
        COLUMN_JMENO = 'jmeno',
        COLUMN_PASSWORD_HASH = 'password',
        COLUMN_EMAIL = 'email',
        COLUMN_ROLE = 'role',
        COLUMN_KONTAKT = 'kontakt',        
        COLUMN_AKTIVNI = 'aktivni',
        COLUMN_ZMENIT_HESLO = 'zmenit_heslo',
        COLUMN_ZMENA = 'zmena';
    /**
     * !!!! POZOR !!!! - inject NEFUNGUJE v modelu, pouze v presenteru
     * Toto je tady tedy spatne !!!!
     * Automaticky volana funkce - nazev injectCokoliv, kde cokoliv je treba i nic,
     * jako v tomto pripade
    /** @var \Nette\Security\User */
    // private $appUser;
    /**
     * https://phpfashion.com/di-a-predavani-zavislosti-presenterum
     * @param \Nette\Security\User $user
     */
    /* v modelu nefunguje !!!!!
    function inject(User $user)
    {
        $this->user = $user;
    }
    */

    public function __construct(
            Nette\Database\Explorer $database,
            Nette\Security\Passwords $passwords
    ) {
        parent::__construct($database);
        $this->passwords = $passwords;
    }    
    
    
    /**
     * Přihlásí uživatele do systému.
     * @param string $username, string $password jméno (případně email) a heslo uživatele
     * @return SimpleIdentity identitu přihlášeného uživatele pro další manipulaci
     * @throws AuthenticationException Jestliže došlo k chybě při prihlašování, např. špatné heslo nebo uživatelské
     *                                 jméno.
     */
    //public function authenticate(string $username, string $password): SimpleIdentity // od PHP 7.4
    public function authenticate(string $username, string $password): Nette\Security\IIdentity
    {

        //list($username, $password) = $credentials; // Extrahuje potřebné parametry.

        // Vykoná dotaz nad databází a vrátí první řádek výsledku nebo false, pokud uživatel neexistuje.
            // pokud je na vstupu email, polozim dotaz na nej
            //if (Validators::isEmail($username)) {
            //    $row = $this->database->table(self::TABLE_NAME)
            //            ->where(self::COLUMN_EMAIL.'=? AND '.self::COLUMN_AKTIVNI.'=\'A\'', $username)
            //            ->fetch();                
            //} else {
        $row = $this->database->table(self::TABLE_NAME)
                ->where('('.self::COLUMN_LOGIN.'=? OR '.self::COLUMN_EMAIL.'=?) AND '.self::COLUMN_AKTIVNI.'=\'A\'', $username,$username)
                ->fetch();                                    
        // Ověření uživatele.
        if (!$row) {
            // Vyhodí výjimku, pokud uživatel neexistuje.
            throw new Nette\Security\AuthenticationException('The username or email is incorrect.', self::IDENTITY_NOT_FOUND);
        } 
        //elseif (!Passwords::verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
        elseif (!$this->passwords->verify($password, $row[self::COLUMN_PASSWORD_HASH])) {            
            // Vyhodí výjimku, pokud je heslo špatně.
            throw new Nette\Security\AuthenticationException('The password is incorrect.', self::INVALID_CREDENTIAL);
        //} elseif (Passwords::needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
        } elseif ($this->passwords->needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
            // Rehashuje heslo.
            $row->update([
                    //self::COLUMN_PASSWORD_HASH => Passwords::hash($password),
                    self::COLUMN_PASSWORD_HASH => $this->passwords->hash($password),
                    self::COLUMN_ZMENA => $this->database->literal('NOW()')
            ]);
        }

        // Příprava uživatelských dat.
        $arr = $row->toArray(); // Extrahuje uživatelská data.
        unset($arr[self::COLUMN_PASSWORD_HASH]); // Odstraní položku hesla z uživatelských dat (kvůli bezpečnosti).

        // Vrátí novou identitu přihlášeného uživatele.
        //return new Nette\Security\Identity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
        //return new SimpleIdentity($row[self::COLUMN_ID],$row[self::COLUMN_ADMIN]==true?'admin':'member', $arr);
        return new SimpleIdentity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
    }

    public function sleepIdentity(IIdentity $identity): IIdentity
    {
        // zde lze pozměnit identitu před zápisem do úložiště po přihlášení,
        // ale to nyní nepotřebujeme
        return $identity;
    }

    public function wakeupIdentity(IIdentity $identity): ?IIdentity
    {
        // aktualizace rolí v identitě
        //$username = $identity->getId();
        $userID = $identity->getId();
                
        // Vykoná dotaz nad databází a vrátí první řádek výsledku nebo false, 
        // pokud uživatel neexistuje, případně pokud byl zakázán
        
        /*
        $row = $this->database->table(self::TABLE_NAME)
                ->where(self::COLUMN_LOGIN.'=? AND '.self::COLUMN_AKTIVNI.'=\'A\'', $username)
                ->fetch();

        // Ověření uživatele.
        if (!$row) {
            // Vyhodí výjimku, pokud uživatel neexistuje.
            //throw new Nette\Security\AuthenticationException('The username is incorrect.', self::IDENTITY_NOT_FOUND);
            return NULL;
        } else {
            return $identity;
        }    
         */    
        $row = $this->database->table(self::TABLE_NAME)
                ->where(self::COLUMN_ID.'=?', $userID)
                ->fetch();

        // Ověření uživatele.
        if (!$row) {
            // Vyhodí výjimku, pokud uživatel neexistuje.
            //throw new Nette\Security\AuthenticationException('The username is incorrect.', self::IDENTITY_NOT_FOUND);
            return NULL;
        } else {          
            // Příprava uživatelských dat.
            // vraci sice spravne obnovene data, ale neulozi je do identity, 
            // pri dalsim wakeup se zase objevi stare data
            // - tak nevim, co je spatne 
            $arr = $row->toArray(); // Extrahuje uživatelská data.
            unset($arr[self::COLUMN_PASSWORD_HASH]); // Odstraní položku hesla z uživatelských dat (kvůli bezpečnosti).
            if ($arr[self::COLUMN_AKTIVNI] == 'N') {
                //throw new Nette\Security\AuthenticationException('The username was disabled.', self::IDENTITY_NOT_FOUND);
                return NULL;
            }
            return new SimpleIdentity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);            

            //$identity->setRoles($row[self::COLUMN_ROLE]); // nefunguje
            // return $identity;
        }        
    }

    public function checkPassword($username, $password)
    {
        // Vykoná dotaz nad databází a vrátí první řádek výsledku nebo false, pokud uživatel neexistuje.
        $row = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_LOGIN, $username)->fetch();

        // Ověření uživatele.
        if (!$row) {
            return false;
        } else {
            //if (!Passwords::verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
            if (!$this->passwords->verify($password, $row[self::COLUMN_PASSWORD_HASH])) {                            
                return false;
            } else {
                return true;
            }
        }
    }
    
    /**
     * Adds new user with default role - for command line use (bin)
     * @param string $username uživatelské jméno (login)
     * @param string $email email uživatele
     * @param string $password heslo
     * @return void
     * @throws InvalidArgumentException
     * @throws DuplicateNameException
     * @throws DuplicateEmailException
     */    
    public function add($username, $email, $password)
    {
        if (!Validators::is($username,'pattern:^[a-zA-Z0-9_]+$')){
            throw new \InvalidArgumentException('Login is not valid.');
        }
        if (!Validators::isEmail($email)){
            throw new \InvalidArgumentException('Email address is not valid.');
        }
        try {
            $this->database->table(self::TABLE_NAME)->insert([
                self::COLUMN_LOGIN => $username,
                //self::COLUMN_PASSWORD_HASH => Passwords::hash($password),
                self::COLUMN_PASSWORD_HASH => $this->passwords->hash($password),
                self::COLUMN_EMAIL => $email,
                self::COLUMN_JMENO => 'unknown',
                self::COLUMN_ROLE  => 'member',
                //self::COLUMN_KONTAKT = 'kontakt',
                self::COLUMN_AKTIVNI => 'A' //,
                //self::COLUMN_ZMENA => new \DateTime,
                //self::COLUMN_ZMENA => $this->database->literal('NOW()')
            ]);
        } catch (Nette\Database\UniqueConstraintViolationException $e) {
            $user = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_LOGIN, $username)->fetch();
            if ($user) {
                throw new DuplicateNameException;                        
            } else {
                $user = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $email)->fetch();
                if ($user) {
                    throw new DuplicateEmailException($user[self::COLUMN_LOGIN]);                                                
                } else {
                    // pro případ, že bych přidal další UNIQUE sloupec a zapomněl ho tady pohlídat
                    throw $e;
                }
            }
        }
    }

    /**
     * Vrátí seznam uživatelů v databázi.
     * @return Selection seznam uživatelů
     */
    public function getUsers()
    {
        return $this->database->table(self::TABLE_NAME)->order(self::COLUMN_ID . ' ASC');
    }

    /**
     * Vrátí uživatele z databáze podle jeho ID.
     * @param string $userID ID uživatele
     * @return bool|mixed|IRow uživatel se zadaným ID nebo false při neúspěchu
     */
    public function getUserByID($userID)
    {
        return $this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID, $userID)->fetch();
    }

    /**
     * Vrátí uživatele z databáze podle jeho loginu.
     * @param string $username login uživatele
     * @return bool|mixed|IRow uživatel se zadaným loginem nebo false při neúspěchu
     */
    public function getUserByLogin($username)
    {
        return $this->database->table(self::TABLE_NAME)->where(self::COLUMN_LOGIN, $username)->fetch();
    }

    /**
     * Vrátí uživatele z databáze podle jeho emailu.
     * @param string $email email uživatele
     * @return bool|mixed|IRow uživatel se zadaným emailem nebo false při neúspěchu
     */
    public function getUserByEmail($email)
    {
        return $this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $email)->fetch();
    }

    /**
     * Vloží nového uživatele. 
     * @param array|ArrayHash $user uživatel
     * @return void
     * @throws InvalidArgumentException
     * @throws DuplicateNameException
     * @throws DuplicateEmailException
     */
    public function registerUser($user)
    {
        // email by se mel validovat na vyssi urovni
        //if (!Validators::isEmail($user[self::COLUMN_EMAIL])){
        //    throw new \InvalidArgumentException('Email address is not valid.');
        //}
        unset($user["passwordVerify"]); // jen pro jistotu, mel by asi resit nadrazeny proces
        if (!isset($user[self::COLUMN_ROLE])) {
            $user[self::COLUMN_ROLE] = "member";
        }        

        // pokud je ve vstupnich datech password, pak je v nehashovane podobe,
        // proto ho prevedeme na hash
        //$user[self::COLUMN_PASSWORD_HASH] = Passwords::hash($user[self::COLUMN_PASSWORD_HASH]);
        $user[self::COLUMN_PASSWORD_HASH] = $this->passwords->hash($user[self::COLUMN_PASSWORD_HASH]);
        try {
            $this->database->table(self::TABLE_NAME)->insert($user);
        } catch (Nette\Database\UniqueConstraintViolationException $e) {
            $usercheck = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_LOGIN, $user[self::COLUMN_LOGIN])->fetch();
            if ($usercheck) {
                throw new DuplicateNameException;                        
            } else {
                $usercheck = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $user[self::COLUMN_EMAIL])->fetch();
                if ($usercheck) {
                    throw new DuplicateEmailException($usercheck[self::COLUMN_LOGIN]);
                } else {
                    // pro případ, že bych přidal další UNIQUE sloupec a zapomněl ho tady pohlídat
                    throw $e;
                }
            }                    			
        }
    }

    /**
     * Provede Update uživatele (bez hesla). 
     * @param array|ArrayHash $user uživatel
     * @return void
     * @throws InvalidArgumentException
     * @throws DuplicateNameException
     * @throws DuplicateEmailException
     */
    public function updateUser($user)
    {
        // email by se mel validovat na vyssi urovni
        //if (!Validators::isEmail($user[self::COLUMN_EMAIL])){
        //    throw new \InvalidArgumentException('Email address is not valid.');
        //}
        
        unset($user["passwordVerify"]); // jen pro jistotu, mel by hlidat presenter
        
        // pokud je ve vstupnich datech password, pak je v nehashovane podobe,
        // proto ho prevedeme na hash
        if (isset($user[self::COLUMN_PASSWORD_HASH])) {
            //$user[self::COLUMN_PASSWORD_HASH] = Passwords::hash($user[self::COLUMN_PASSWORD_HASH]);
            $user[self::COLUMN_PASSWORD_HASH] = $this->passwords->hash($user[self::COLUMN_PASSWORD_HASH]);
        }
        if (!isset($user[self::COLUMN_ZMENA])) {
            $user[self::COLUMN_ZMENA] = $this->database->literal('NOW()');
        }                
        try {
            $this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID, $user[self::COLUMN_ID])->update($user);
        } catch (Nette\Database\UniqueConstraintViolationException $e) {
            
            // mame chybu unikatniho klice
            // je treba rozdelit na volani UPDATE s loginem, emailem, pripadne dalsim klicem
            // overim, k jake duplicite vlastne doslo
            
            // vytahnu aktualniho uzivatele
            $usercheck = $this->database->table(self::TABLE_NAME)->
                    where(self::COLUMN_ID, $user[self::COLUMN_ID])
                    ->fetch();
            if ($usercheck) {
                if (($usercheck[self::COLUMN_LOGIN] == $user[self::COLUMN_LOGIN]) && 
                    ($usercheck[self::COLUMN_EMAIL] == $user[self::COLUMN_EMAIL])) {
                    // login i email je stejny, tedy mame jinou chybu - 
                    // neosetreneho sloupce
                    throw $e;
                } else {
                    // overim chybu duplicity loginu
                    $usercheck2 = $this->database->table(self::TABLE_NAME)->
                            where(self::COLUMN_ID.' <> ? AND '.self::COLUMN_LOGIN.' = ?', $user[self::COLUMN_ID], $user[self::COLUMN_LOGIN])
                            ->fetch();
                    if ($usercheck2) {
                        throw new DuplicateNameException;                        
                    } else {
                        // login v tabulce neexistuje, proto se jedna o chybu 
                        // duplicity emailu
                        throw new DuplicateEmailException($usercheck[self::COLUMN_LOGIN]);
                    }
                }
            } else {      
                throw $e; // nejaka divna, neosetrena chyba, jen preposlu
            }                    			
        }
    }

    /**
     * Provede Update hesla uživatele (bez znalosti hesla - jen pro admina). 
     * @param string $userID ID uživatele
     * @param string $password ID uživatele
     * @return int
     */
    public function updateUserPassword($userID, $password)
    {
        //$password = Passwords::hash($password);
        $password = $this->passwords->hash($password);
        return $this->database->table(self::TABLE_NAME)
                ->where(self::COLUMN_ID, $userID)
                ->update([
                    self::COLUMN_PASSWORD_HASH => $password,
                    self::COLUMN_ZMENA => $this->database->literal('NOW()')
                ]);
    }

    /*
            if (!$user[self::COLUMN_ID]) { // vložit jako nového uživatele
                $this->database->table(self::TABLE_NAME)->insert($user);
            } else { // uložit stávajícího
                $this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID, $user[self::COLUMN_ID])->update($user);
            }
*/
    /**
     * Odstraní uživatele.
     * @param string $userID ID uživatele
     */
    public function removeUser($userID)
    {
        $this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID, $userID)->delete();
    }
}


/**
* Výjimka pro duplicitní uživatelské jméno.
* @package App\Model
*/
class DuplicateNameException extends \Exception
{
    /** Konstruktor s definicích výchozí chybové zprávy. */
    public function __construct()
    {
            parent::__construct();
            //$this->message = 'Uživatel s tímto jménem je již zaregistrovaný.';
            $this->message = 'Username is already registered.';
    }

}

/**
* Výjimka pro duplicitní email.
* @package App\Model
*/
class DuplicateEmailException extends \Exception
{
    /** Konstruktor s definicích výchozí chybové zprávy. */
    // public function __construct(string $username = "")
    // public function __construct()
    public function __construct($username)
    {
        parent::__construct();
        //$this->message = 'Tento email je již zaregistrovaný na uživatele <'.$username.'>';
        //$this->message = 'This email is already used by user <'.$username.'>';
        $this->message = $username;
    }

}
