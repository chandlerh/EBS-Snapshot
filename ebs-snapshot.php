    <?php

        /**
         * Chandler Hoisington
         * February 9, 2012
         *
         * This script is designed to create a snapshot of an EBS volume and delete old ones.
         *
         * Please note:
         * we are only locking the MySQL tables, the volume can still have activity and
         * if you snapshot during activity you could end up with corrupted data.
         *
         */

        //ENV Variables
        putenv("EC2_URL=https://ec2.us-west-2.amazonaws.com");
        putenv("EC2_HOME=PATH_TO_TOOLS/ec2-api-tools-1.5.2.4/");

        $EC2_TOOLS_PATH = "PATH_TO_TOOLS/ec2-api-tools-1.5.2.4/bin/";
        $CERT='PATH TO AWS CERT';
        $KEY='PATH TO AWS KEY';
        $SNAP_DESCRIPTION = 'MY_VOLUME' . date("Y-m-d");
        $VOLUME = 'AWS VOLUME ID';
        $SNAP_TO_KEEP = 10;

        //Make a snapshot
        try {
            $dbh = new PDO('mysql:host=MYSQL_HOST;dbname=DBNAME', 'USERNAME', 'PASSWORD');
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Connected to database\n";

            //Prepare statements
            $psQueryFlush = $dbh->prepare("FLUSH LOCAL TABLES");
            $psQueryFlushWithLock = $dbh->prepare("FLUSH TABLES WITH READ LOCK");
            $psQueryUnlock = $dbh->prepare("UNLOCK TABLES");

            echo "Flushing tables and locking them.\n";
            //Setup for snapshot
            $psQueryFlush->execute();
            $psQueryFlushWithLock->execute();

            echo "Creating snapshot.\n";
            //Create Snapshot
            $output = array();
            exec($EC2_TOOLS_PATH . 'ec2-create-snapshot ' . $VOLUME . ' -K ' . $KEY . ' -C ' . $CERT . ' -d ' . $SNAP_DESCRIPTION, $output);
            print_r($output);

            echo "unlocking tables.\n";
            $psQueryUnlock->execute();

            //Close the connection
            $dbh = null;

            echo "done.\n";

        } catch (PDOException $e) {

            echo $e->getTraceAsString();
            echo $e->getMessage();

        }

        //Now we delete old ones

        $output = array();
        exec($EC2_TOOLS_PATH . 'ec2-describe-snapshots -K ' . $KEY . ' -C ' . $CERT . " | grep " . $VOLUME, $output);

        $snapshots = array();
        foreach ($output as $snap) {
            $snapshots[] = explode("\t",$snap);
        }

        // convert to a unix timestamp and add to array with snapshot id and date
        $i = 0;
        $snaps = array();
        foreach ($snapshots as $s) {
            $snaps[$i][0] = strtotime($s[4]);
            $snaps[$i][1] = $s[1];
            $i++;
        }

        //Sort that bad boy
        $snaps = mySort($snaps,0);

        //Delete if there are more than $SNAP_TO_KEEP
        if (sizeof($snaps) > $SNAP_TO_KEEP) {
            for ($i = $SNAP_TO_KEEP; $i < sizeof($snaps); $i++) {
                echo "Deleting snapshot " . $snaps[$i][1];
                exec($EC2_TOOLS_PATH . 'ec2-delete-snapshot ' . $snaps[$i][1] . ' -K ' . $KEY . ' -C ' . $CERT);
            }
        } else {
            echo "Not deleting any snapshots. Only " . sizeof($snaps) . " for volume " . $VOLUME . ".";
        }

        //Sort function
        function mySort($multiArray, $sortValue) {
            $i = 0;
            foreach ($multiArray as $smallArray) {
                    $dates[$i] = $smallArray[$sortValue];
                    $i++;
            }
            asort($dates);
            foreach ($multiArray as $mAKey => $mAValue) {
                foreach($dates as $dKey => $dValue) {
                    $multiArray[$dKey][0] = $dValue;
                }
            }
            return $multiArray;
        }

    ?>

