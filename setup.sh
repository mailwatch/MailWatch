#!/bin/bash
# Bash Menu Script Example
InstallFilesFolder=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
MailScannerVersion="5.0.3-7"

if cat /etc/*release | grep ^NAME | grep CentOS; then
    OS="CentOS"
    PM="yum"
    MailScannerDownloadPath="https://s3.amazonaws.com/msv5/release/MailScanner-$MailScannerVersion.rhel.tar.gz"
elif cat /etc/*release | grep ^NAME | grep Red; then
    OS="RedHat"
    PM="yum"
    MailScannerDownloadPath="https://s3.amazonaws.com/msv5/release/MailScanner-$MailScannerVersion.rhel.tar.gz"
elif cat /etc/*release | grep ^NAME | grep Fedora; then
    OS="Fedora"
    PM="yum"
    MailScannerDownloadPath="https://s3.amazonaws.com/msv5/release/MailScanner-$MailScannerVersion.rhel.tar.gz"
elif cat /etc/*release | grep ^NAME | grep Ubuntu; then
    OS="Ubuntu"
    PM="apt-get"
    MailScannerDownloadPath="https://s3.amazonaws.com/msv5/release/MailScanner-$MailScannerVersion.deb.tar.gz"
elif cat /etc/*release | grep ^NAME | grep Debian ; then
    OS="Debian"
    PM="apt-get"
    MailScannerDownloadPath="https://s3.amazonaws.com/msv5/release/MailScanner-$MailScannerVersion.deb.tar.gz"
else
    echo "OS NOT SUPPORTED - Please perform a manual install"
    exit 1;
fi



EndNotice=""
Webuser="www-data"


function logprint {
    echo "$1"
    echo "$1" >> /root/mailwatchInstall.log
}
logprint "Clearing temp dir"
rm -rf /tmp/mailwatchinstall/*


read -p "Install/upgrade MailScanner version $MailScannerVersion?:(y/n)[y]: " installMailScanner
if [ -z $installMailScanner ] || "$installMailScanner" == "y"; then
    logprint "Starting MailScanner install"
    mkdir -p /tmp/mailwatchinstall/mailscanner
    logprint "Downloading current MailScanner release $MailScannerVersion:"
    wget -O /tmp/mailwatchinstall/mailscanner/MailScanner.deb.tar.gz  "$MailScannerDownloadPath"
    logprint "Extracting mailscanner files:"
    tar -xzf /tmp/mailwatchinstall/mailscanner/MailScanner.deb.tar.gz -C /tmp/mailwatchinstall/mailscanner/
    logprint "Starting MailScanner install script"
    /tmp/mailwatchinstall/mailscanner/MailScanner-$MailScannerVersion/install.sh
    logprint "MailScanner install finished."
    $EndNotice="$EndNotice \n * Adjust /etc/MailScanner.conf to your needs \n * Set run_mailscanner=1 in /etc/MailScanner/defaults"
    sleep 1
    break
    ;;
else
   logprint "Not installing MailScanner"
fi

logprint "Installing Encoding::FixLatin"
cpan -i Encoding::FixLatin

##ask directory for web files
read -p "In what location should MailWatch be installed?[/var/www/mailscanner/]:" WebFolder
if [ -z $WebFolder ]; then
    WebFolder="/var/www/mailscanner/"
fi
logprint "Using web directory $WebFolder"

/etc/init.d/mailscanner stop

if [ -d $WebFolder ]; then
   read -p "Folder $WebFolder already exists. Content will get deleted. Do you want to continue?(y/n)[n]: " response
   if [ -z $response ]; then
       logprint "Stopping setup on user request"
       exit
   elif [ "$response" == "n" ]; then
       logprint "Stopping setup on user request"
       exit
   fi
   logprint "Clearing web directory"
   rm -R $WebFolder
fi

logprint "Setting up sql credentials"
#get sql credentials
read -p "SQL user for mailwatch[mailwatch]:" SqlUser
if [ -z $SqlUser ]; then
    SqlUser="mailwatch"
fi
read -p "SQL password for mailwatch[mailwatch]:" SqlPwd
if [ -z $SqlPwd ]; then
    SqlPwd="mailwatch"
fi
read -p "SQL database for mailwatch[mailscanner]:" SqlDb
if [ -z $SqlDb ]; then
    SqlDb="mailscanner"
fi
read -p "SQL host of database[localhost]:" SqlHost
if [ -z $SqlHost ]; then
    SqlHost="localhost"
fi
logprint "Using sql credentials user: $SqlUser; password: $SqlPwd; db: $SqlDb; host: $SqlHost"

if ! type "mysqld" > /dev/null 2>&1; then
    read -p "No mysql server found. Do you want to install mariadb as sql server?(y/n)[y]: " response
    if [ -z $response ] || [ $response == "y" ]; then
        logprint "Start install of mariadb"
        $PM install mariadb-server mariadb-client
        mysqlInstalled="1"
    else
        mysqlInstalled="0"
        logprint "Not installing mariadb."
    fi
else
    mysqlInstalled="1"
    logprint "Found installed mysql server and will use that"
fi

if [ "$mysqlInstalled" == "1"]; then
    read -p "Root sql user (with rights to create db)[root]:" SqlRoot
    if [ -z $SqlRoot ]; then
        SqlRoot="root"
    fi
    logprint "Creating sql database and setting permission. You now need to enter the password of the root sql user twice"
    mysql -u $SqlRoot -p < "$InstallFilesFolder/create.sql"
    mysql -u $SqlRoot -p --execute="GRANT ALL ON $SqlDb.* TO $SqlUser@localhost IDENTIFIED BY '$SqlPwd'; GRANT FILE ON *.* TO $SqlUser@localhost IDENTIFIED BY '$SqlPwd'; FLUSH PRIVILEGES"

    read -p "Enter an admin user for the MailWatch web interface: " MWAdmin
    read -p "Enter password for the admin: " MWAdminPwd
    logprint "Create MailWatch web gui admin"
    mysql -u $SqlUser -p$SqlPwd $SqlDb --execute="REPLACE INTO users SET username = '$MWAdmin', password = MD5('$MWAdminPwd'), fullname = 'Admin', type = 'A';"
else 
    echo "You have to create the database yourself!"
    EndNotice="$EndNotice \n * create the database, a sql user with access to the db and following properties user: $SqlUser; password: $SqlPwd; db: $SqlDb; host: $SqlHost"
    EndNotice="$EndNotice \n * create an admin account for the web gui"
fi
    
#copy web files
logprint "Moving MailWatch web files to new folder and setting permissions"
mv "$InstallFilesFolder/mailscanner/" $WebFolder
chown root:mtagroup $WebFolder/images
chmod ug+rwx $WebFolder/images
chown root:mtagroup $WebFolder/images/cache
chmod ug+rwx $WebFolder/images/cache
chown root:mtagroup $WebFolder/temp
chmod g+rw $WebFolder/temp

#test existing webserver
if [[ type "httpd" > /dev/null 2>&1 || type "apache2" > /dev/null 2>&1 ]]; then
    WebServer="apache"
    logprint "Detected installed web server apache. We will use it for MailWatch"
elif type "nginx" > /dev/null 2>&1; then
    WebServer="nginx"
    logprint "Detected installed web server nginx. We will use it for MailWatch"
else
    echo "We're unable to find your webserver.  We support Apache and Nginx";echo;
    echo "Do you wish me to install a webserver?"
    echo "1 - Apache"
    echo "2 - Nginx"
    echo "n - do not install or configure"
    echo;
    read -r -p "Select Webserver: " response
    if [[ $response =~ ^([nN][oO])$ ]]; then
        #do not install or configure webserver
        WebServer="skip"
    elif [ $response == 1 ]; then
        #Apache
        logprint "Installing apache"
        $PM install apache2
        WebServer="apache"
    elif [ $response == 2 ]; then
        #Nginx
        logprint "Installing nginx"
        $PM install nginx
        WebServer="nginx"
    else 
        WebServer="skip"
    fi
fi

read -p "MailWatch requires the following php packages. Do you want to install them if missing?(y/n)[y]: " installPhp
if [ -z $installPhp ] || [ "$installPhp" == "y" ]; then
    logprint "Installing required php packages"
    $PM install php5 php5-gd and php5-mysqlnd
else
    logprint "Not installing php packages. You have to check it manually."
    EndNotice= "$EndNotice \n * check for installed php5 php5-gd and php5-mysqlnd"
fi

case $WebServer in
    "apache")
        logprint "Creating config file in /etc/apache2/conf-enabled/mailwatch.conf"
        cat > /etc/apache2/conf-enabled/mailwatch.conf << EOF
Alias /mailwatch/ "$WebFolder/"
<Directory "$WebFolder/">
  Require all granted
</Directory>
EOF
        logprint "Enable ssl for apache and reload"
        a2enmod ssl
        /etc/init.d/apache2 reload
        sleep 1
        break
        ;;
    "nginx")
       #TODO
        logprint "not available yet"
        sleep 1
        break
        ;;
    "skip")
        logprint "Skipping web server install"
        EndNotice="$EndNotice \n * you need to configure your webserver for directory $WebFolder."
        sleep 1
        break
        ;;
esac

#todo install web server, sql db(set the above)
#todo create/modify group mtagroup to include mta user, web server user, av user, mailscanner user

#apply general MailWatch settings
logprint "Adjust MailWatch conf.php"
cp "$WebFolder/conf.php.example" "$WebFolder/conf.php"
sed -i -e "s~^define('MAILWATCH_HOME', '.*')~define('MAILWATCH_HOME', '$WebFolder')~" $WebFolder/conf.php
sed -i -e "s/^define('DB_USER', '.*')/define('DB_USER', '$SqlUser')/" $WebFolder/conf.php
sed -i -e "s/^define('DB_PASS', '.*')/define('DB_PASS', '$SqlPwd')/" $WebFolder/conf.php
sed -i -e "s/^define('DB_HOST', '.*')/define('DB_HOST', '$SqlHost')/" $WebFolder/conf.php
sed -i -e "s/^define('DB_NAME', '.*')/define('DB_NAME', '$SqlDb')/" $WebFolder/conf.php

#apply adjustments for MTAs
PS3='Which MTA do you want to use with MailWatch?: (it should already be installed):'
options=("sendmail" "postfix" "exim" "skip")
select opt in "${options[@]}"
do
    logprint "Selected mta $opt"
    case $opt in
        "sendmail")
            logprint "Not yet supported"
#TODO
            sleep 1
            break
            ;;
        "postfix")
            logprint "Configure MailScanner for use with postfix"
            echo "header_checks = regexp:/etc/postfix/header_checks" >> /etc/postfix/main.cf
            echo "/^Received:/ HOLD" >> /etc/postfix/header_checks
            logprint "Restarting postfix"
            #restart required to create hold folder
            /etc/init.d/postfix restart
            logprint "Setting file permissions for use of postfix"
            mkdir -p /var/spool/MailScanner/spamassassin/
            chown -R postfix:mtagroup /var/spool/MailScanner/spamassassin/
            chown -R postfix:www-data /var/spool/postfix/incoming/
            chown -R postfix:www-data /var/spool/postfix/hold
            chmod -R g+r /var/spool/postfix/hold
            chmod -R g+r /var/spool/postfix/incoming/
            chown -R postfix.postfix /var/spool/MailScanner/incoming
            chown -R postfix.postfix /var/spool/MailScanner/quarantine

            logprint "Generating MailWatch config for MailScanner"
            cat > /etc/MailScanner/conf.d/mailwatch.conf << EOF
Run As User = postfix
Run As User = mtagroup
MTA = postfix
Incoming Work User = postfix
Incoming Work Group = mtagroup
Incoming Work Permissions = 0660
Detailed Spam Report = yes
Quarantine Whole Message = yes
Quarantine Whole Messages As Queue Files = no
Include Scores In SpamAssassin Report = yes
Quarantine User = postfix
Quarantine Group = mtagroup
Quarantine Permissions = 0664
Always Looked Up Last = &MailWatchLogging
Is Definitely Not Spam = &SQLWhitelist
Is Definitely Spam = &SQLBlacklist
Incoming Queue Dir = /var/spool/postfix/hold
Outgoing Queue Dir = /var/spool/postfix/incoming
SpamAssassin User State Dir = /var/spool/MailScanner/spamassassin
EOF
            sleep 1
            break
            ;;
            
        "exim")
            logprint "Configure MailScanner for use with exim"
# TODO exim config modifications that differ from default config            
            logprint "Generating MailWatch config for MailScanner"
            cat > /etc/MailScanner/conf.d/mailwatch.conf << EOF
Run As User = Debian-exim
Run As Group = Debian-exim
MTA = exim
Incoming Work User = Debian-exim
Incoming Work Group = mtagroup
Incoming Work Permissions = 0660
Quarantine Whole Message = yes
Quarantine Whole Messages As Queue Files = no
Include Scores In SpamAssassin Report = yes
Quarantine User = Debian-exim
Quarantine Group = mtagroup
Quarantine Permissions = 0644
Always Looked Up Last = &MailWatchLogging
Is Definitely Not Spam = &SQLWhitelist
Is Definitely Spam = &SQLBlacklist
Incoming Queue Dir = /var/spool/exim4/input
Outgoing Queue Dir = /var/spool/exim4_outgoing/input

Queue Scan Interval = 6
Restart Every = 3600
Sendmail = /usr/sbin/exim4
Sendmail2 = /usr/sbin/exim4 -DOUTGOING
Max Unscanned Messages Per Scan = 50
Max Unsafe Messages Per Scan = 50
Max Normal Queue Size = 2000
Deliver Unparsable TNEF = yes
Find UU-Encoded Files = yes
Max Children = 10
EOF
#TODO
            sleep 1
            break
            ;;
        "skip")
            sleep 1
            break
            ;;
    esac
done

logprint "Adjusting perl files"
sed -i -e "s/my (\$db_name) = '.*'/my (\$db_name) = '$SqlDb'/" "$InstallFilesFolder/MailScanner_perl_scripts/MailWatch.pm"
sed -i -e "s/my (\$db_host) = '.*'/my (\$db_host) = '$SqlHost'/" "$InstallFilesFolder/MailScanner_perl_scripts/MailWatch.pm"
sed -i -e "s/my (\$db_user) = '.*'/my (\$db_user) = '$SqlUser'/" "$InstallFilesFolder/MailScanner_perl_scripts/MailWatch.pm"
sed -i -e "s/my (\$db_pass) = '.*'/my (\$db_pass) = '$SqlPwd'/" "$InstallFilesFolder/MailScanner_perl_scripts/MailWatch.pm"
sed -i -e "s/my (\$db_name) = '.*'/my (\$db_name) = '$SqlDb'/" "$InstallFilesFolder/MailScanner_perl_scripts/SQLSpamSettings.pm"
sed -i -e "s/my (\$db_host) = '.*'/my (\$db_host) = '$SqlHost'/" "$InstallFilesFolder/MailScanner_perl_scripts/SQLSpamSettings.pm"
sed -i -e "s/my (\$db_user) = '.*'/my (\$db_user) = '$SqlUser'/" "$InstallFilesFolder/MailScanner_perl_scripts/SQLSpamSettings.pm"
sed -i -e "s/my (\$db_pass) = '.*'/my (\$db_pass) = '$SqlPwd'/" "$InstallFilesFolder/MailScanner_perl_scripts/SQLSpamSettings.pm"
sed -i -e "s/my (\$db_name) = '.*'/my (\$db_name) = '$SqlDb'/" "$InstallFilesFolder/MailScanner_perl_scripts/SQLBlackWhiteList.pm"
sed -i -e "s/my (\$db_host) = '.*'/my (\$db_host) = '$SqlHost'/" "$InstallFilesFolder/MailScanner_perl_scripts/SQLBlackWhiteList.pm"
sed -i -e "s/my (\$db_user) = '.*'/my (\$db_user) = '$SqlUser'/" "$InstallFilesFolder/MailScanner_perl_scripts/SQLBlackWhiteList.pm"
sed -i -e "s/my (\$db_pass) = '.*'/my (\$db_pass) = '$SqlPwd'/" "$InstallFilesFolder/MailScanner_perl_scripts/SQLBlackWhiteList.pm"

logprint "Copying perl files to MailScanner"
cp "$InstallFilesFolder"/MailScanner_perl_scripts/* /etc/MailScanner/custom/

logprint "Restart mailscanner service"
/etc/init.d/mailscanner restart
#todo relay files
logprint "Install finished!"
logprint "Next steps you have to do are:"
logprint "$EndNotice"
logprint " * adjust your mta and web server configs"
logprint " * adjust the MailWatch config $WebFolder/conf.php"
echo ""
echo "You can find the log file at /root/mailwatchInstall.log"
