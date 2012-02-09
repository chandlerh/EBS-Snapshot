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
         * Not responsible if you delete all the data.
         *
         * Requirements: PHP, AWS-API-TOOLS, php-pdo
         *
         *
         */

        //ENV Variables
        putenv("EC2_URL=https://ec2.us-west-2.amazonaws.com");
        putenv("EC2_HOME=PATH_TO_EC2_TOOLS");

        $EC2_TOOLS_PATH = "PATH_TO_EC2_TOOLS";
        $CERT='AWS_CERT';
        $KEY='AWS_KEY';
        $SNAP_DESCRIPTION = 'SNAPSHOT_DESCRIPTION' . date("Y-m-d");
        $VOLUME = 'VOLUME_TO_BE_SNAPSHOTTED_ID (vol-xxxxxx)';
        $SNAP_TO_KEEP = 10;
        $DB_HOST = "10.x.x.x.";
        $DB_NAME = "";
        $DB_USER = "";
        $DB_PASS = "";

        //Make a snapshot
        //TODO: Make an array of volumes and SNAP_TO_KEEP values and loop through script for each one.
        try {
            $dbh = new PDO('mysql:host=' . $DB_HOST . ';' . 'dbname=' . $DB_NAME, $DB_USER, $DB_PASS);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Connected to database\n";

            //Prepare statements
            $psQueryFlush = $dbh->prepare("FLUSH LOCAL TABLES");
            $psQueryFlushWithLock = $dbh->prepare("FLUSH TABLES WITH READ LOCK");
            $psQueryUnlock = $dbh->prepare("UNLOCK TABLES");

            echo "Flushing tables and locking them.\n";
            $psQueryFlush->execute();
            $psQueryFlushWithLock->execute();

            echo "Creating snapshot.\n";
            $output = array();
            exec($EC2_TOOLS_PATH . 'ec2-create-snapshot ' . $VOLUME . ' -K ' . $KEY . ' -C ' . $CERT . ' -d ' . $SNAP_DESCRIPTION, $output);
            print_r($output);

            echo "unlocking tables.\n";
            $psQueryUnlock->execute();

            $dbh = null;

            echo "done.\n";

        } catch (PDOException $e) {

            echo $e->getTraceAsString();
            echo $e->getMessage();

        }

        //Now we delete old ones
        echo "Looking if we need to delete old snapshots...\n";
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

        $snaps = mySort($snaps,0);

        //Delete if there are more than $SNAP_TO_KEEP
        if (sizeof($snaps) > $SNAP_TO_KEEP) {
            echo "More than " . $SNAP_TO_KEEP . "deleting some snapshots...\n";
            for ($i = $SNAP_TO_KEEP; $i < sizeof($snaps); $i++) {
                echo "Deleting snapshot " . $snaps[$i][1] . "\n";
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

