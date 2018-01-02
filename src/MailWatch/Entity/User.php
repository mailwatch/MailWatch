<?php

namespace MailWatch\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;


/**
 * @ORM\Entity(repositoryClass="MailWatch\Repository\UserRepository")
 * @ORM\Table(name="users")
 */
class User implements UserInterface, EquatableInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="bigint", unique=true)
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=191)
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $fullname;

    /**
     * @ORM\Column(type="string", length=1, nullable=true)
     */
    private $type;


    /**
     * @ORM\Column(type="string", length=32, name="resetid", nullable=true)
     */
    private $resetId;

    /**
     * @ORM\Column(type="bigint", name="resetexpire", nullable=true)
     */
    private $resetExpire;

    /**
     * @ORM\Column(type="bigint", name="lastreset", nullable=true)
     */
    private $lastReset;

    /**
     * @ORM\Column(type="bigint", name="login_expiry")
     */
    private $lastExpiry;

    /**
     * @ORM\Column(type="bigint", name="last_login")
     */
    private $lastLogin;

    /**
     * @ORM\Column(type="smallint", name="login_timeout")
     */
    private $loginTimeout;

    public function __construct($id, $username, $password, $fullname, $type)
    {
        $this->id = $id;
        $this->username = $username;
        $this->password = $password;
        $this->fullname = $fullname;
        this.setType($type);
    }


    public function getId()
    {
        return $this->id;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getFullname()
    {
        return $this->fullname;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getResetId()
    {
        return $this->resetId;
    }

    public function getResetExpire()
    {
        return $this->resetExpire;
    }

    public function getLastReset()
    {
        return $this->lastReset;
    }

    public function getLastExpiry()
    {
        return $this->lastExpiry;
    }

    public function getLastLogin()
    {
        return $this->lastLogin;
    }

    public function getLoginTimeout()
    {
        return $this->loginTimeout;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function setFullname($fullname)
    {
        $this->fullname = $fullname;
    }

    public function setType($type)
    {
        if(!in_array($type, ['A','D','U','R','H'])) {
            throw new \InvalidArgumentException();
        }
        $this->type = $type;
    }

    public function setResetId($resetId)
    {
        $this->resetId = $resetId;
    }

    public function setResetExpire($resetExpire)
    {
        $this->resetExpire = $resetExpire;
    }

    public function setLastReset($lastReset)
    {
        $this->lastReset = $lastReset;
    }

    public function setLastExpiry($lastExpiry)
    {
        $this->lastExpiry = $lastExpiry;
    }

    public function setLastLogin($lastLogin)
    {
        $this->lastLogin = $lastLogin;
    }

    public function setLoginTimeout($loginTimeout)
    {
        $this->loginTimeout = $loginTimeout;
    }

    public function eraseCredentials()
    {
        $this->password = null;
    }

    public function getRoles()
    {
        return array("ROLE_".$this->type); //TODO: roles have to start with ROLE_ change, mirgrate db roles
    }

    public function getSalt()
    {
        return "";
    }

    public function isEqualTo(UserInterface $user): bool
    {
        if ($this->password !== $user->getPassword()) {
            return false;
        }

        if ($this->username !== $user->getUsername()) {
            return false;
        }
        return true;
    }
}
