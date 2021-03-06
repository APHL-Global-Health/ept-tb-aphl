# README #

Welcome to the Open Source repository of the e-Proficiency Testing (ePT) software

### How do I get set up? ###

* [Download the ePT Source Code](https://github.com/deforay/ept/releases) and put it into your server's root folder (www or htdocs). 
* Create a database and [import the sql file that you can find in the downloads section of this repository](https://github.com/deforay/ept/releases)
* Modify the config file (application/configs/application.ini) and update the database parameters
* Create a virtual host pointing to the public folder of the source code

#### Setup Cron Jobs on Server ####
`
$ sudo crontab -u www-data -e
`

enter the following jobs
```
*/2 * * * * php -f /var/www/ept-2.4/cron/SendMobilePushNotifications.php
*/2 * * * * php -f /var/www/ept-2.4/cron/SendMailAlerts.php
0 0 6 1/1 * ? * php -f /var/www/ept-2.4/cron/SendParticipationDataToGxAlert.php
```


### Next Steps ###

* Once you have the software set up, you can visit the admin panel http://ept/admin and log in with the credentials eptmanager@gmail.com and 123
* Now you can start adding Participants, Participant logins, PE Surveys, Shipments etc.

### Who do I talk to? ###

* You can reach us at amit (at) deforay (dot) com
