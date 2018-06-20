<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2018  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * In addition, as a special exception, the copyright holder gives permission to link the code of this program with
 * those files in the PEAR library that are licensed under the PHP License (or with modified versions of those files
 * that use the same license as those files), and distribute linked combinations including the two.
 * You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 * PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 * your version of the program, but you are not obligated to do so.
 * If you do not wish to do so, delete this exception statement from your version.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace MailWatch\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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
        this . settype($type);
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
        if (!in_array($type, ['A', 'D', 'U', 'R', 'H'])) {
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
        return ['ROLE_' . $this->type]; //TODO: roles have to start with ROLE_ change, migrate db roles
    }

    public function getSalt()
    {
        return '';
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
