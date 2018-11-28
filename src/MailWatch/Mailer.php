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

namespace MailWatch;

class Mailer
{
    /**
     * @param string $email
     * @param string $html
     * @param string $text
     * @param string $subject
     * @param bool $pwdreset
     *
     * @return mixed
     */
    public static function send($email, $html, $text, $subject, $pwdreset = false)
    {
        $mime = new \Mail_mime("\n");
        if (true === $pwdreset && (\defined('PWD_RESET_FROM_NAME') && \defined('PWD_RESET_FROM_ADDRESS') && PWD_RESET_FROM_NAME !== '' && PWD_RESET_FROM_ADDRESS !== '')) {
            $sender = PWD_RESET_FROM_NAME . '<' . PWD_RESET_FROM_ADDRESS . '>';
        } else {
            $sender = QUARANTINE_REPORT_FROM_NAME . ' <' . MAILWATCH_FROM_ADDR . '>';
        }
        $hdrs = [
            'From' => $sender,
            'To' => $email,
            'Subject' => $subject,
            'Date' => date('r'),
        ];
        $mime_params = [
            'text_encoding' => '7bit',
            'text_charset' => 'UTF-8',
            'html_charset' => 'UTF-8',
            'head_charset' => 'UTF-8',
        ];
        $mime->addHTMLImage(MAILWATCH_HOME . '/' . IMAGES_DIR . MW_LOGO, 'image/png', MW_LOGO);
        $mime->setTXTBody($text);
        $mime->setHTMLBody($html);
        $body = $mime->get($mime_params);
        $hdrs = $mime->headers($hdrs);
        $pearMail = new \Mail_smtp(static::getParameters());

        return $pearMail->send($email, $hdrs, $body);
    }

    /**
     * @return array
     */
    public static function getParameters(): array
    {
        $mail_param = ['host' => MAILWATCH_MAIL_HOST, 'port' => MAILWATCH_MAIL_PORT];
        if (\defined('MAILWATCH_SMTP_HOSTNAME')) {
            $mail_param['localhost'] = MAILWATCH_SMTP_HOSTNAME;
        }

        return $mail_param;
    }
}
