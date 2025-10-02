Place the files in the root catalogue of your website.


/*-------------------- Version 1.00 ------------------------------------//
 A php script to scan your filesystem for changes. Can be useful to discover if a website has been hacked or searching for viruses. 
https://sitescanner.t-qr.com/

GNU General Public License v3.0

Add &nolog to url for skip writing to logfile.
Add &nomtime to skip check modified time, just filesize will be checked.

The first time this script runs it will generate a token to access the script, save it. If you need a new token simply remove token.php from the scandir folder. 

The second time the script runs it will create a new table and populate it with the list of all your files in the same directory as this script. It will also create an empty logfile in the scandir folder. This will take some time.

The third time you will only see the changes in the filesystem and it will be written to the logfile. 

No logrotation yet
//-------------------------------------------------------------------------*/
