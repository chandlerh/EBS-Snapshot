    <?php

    /**
     * @author Chandler Hoisington
     */
    class EbsController {

            private $ec2ToolsPath;
            private $cert;
            private $key;
            private $snapDescription;
            private $dbHost;
            private $dbName;
            private $dbUser;
            private $dbPass;
            private $volume;
            private $snapToKeep;

            /**
             *
             * Constructor
             *
             * @param $ec2ToolsPath
             * @param $cert
             * @param $key
             * @param $snapDescription
             * @param $dbHost
             * @param $dbName
             * @param $dbUser
             * @param $dbPass
             * @param $volume
             * @param $snapToKeep
             */
            public function __construct($ec2ToolsPath, $cert, $key, $snapDescription, $dbHost, $dbName, $dbUser, $dbPass, $volume, $snapToKeep){
                $this->ec2ToolsPath = $ec2ToolsPath;
                $this->cert = $cert;
                $this->key = $key;
                $this->snapDescription = $snapDescription;
                $this->dbHost = $dbHost;
                $this->dbName = $dbName;
                $this->dbUser = $dbUser;
                $this->dbPass = $dbPass;
                $this->volume = $volume;
                $this->snapToKeep = $snapToKeep;
            }

            /**
             * Function to create and delete snapshots
             */
            public function createAndDelete() {
                $this->createSnapshot();
                $this->deleteOldSnapshots();
            }


            /**
             * Function to create new snapshot
             */
            private function createSnapshot() {

                try {
                    $dbh = new PDO('mysql:host=' . $this->dbHost . ';' . 'dbname=' . $this->dbName, $this->dbUser, $this->dbPass);
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
                    exec($this->ec2ToolsPath . 'ec2-create-snapshot ' . $this->volume . ' -K ' . $this->key . ' -C ' . $this->key . ' -d ' . $this->snapDescription, $output);
                    print_r($output);

                    echo "unlocking tables.\n";
                    $psQueryUnlock->execute();

                    $dbh = null;

                    echo "done.\n";

                } catch (PDOException $e) {

                    echo $e->getTraceAsString();
                    echo $e->getMessage();

                }
            }

            /**
             * Function to delete the old snapshots
             */
            private function deleteOldSnapshots() {

                echo "Looking if we need to delete old snapshots...\n";
                $output = array();
                exec($this->ec2ToolsPath . 'ec2-describe-snapshots -K ' . $this->key . ' -C ' . $this->cert . " | grep " . $this->volume, $output);

                $snapshots = array();
                foreach ($output as $snap) {
                    $snapshots[] = explode("\t",$snap);
                }

                // convert to a unix timestamp
                $i = 0;
                $snaps = array();
                foreach ($snapshots as $s) {
                    $snaps[$i][0] = strtotime($s[4]);
                    $snaps[$i][1] = $s[1];
                    $i++;
                }

                $snaps = $this->mySort($snaps,0);

                //Delete if there are more than $this->snapToKeep
                if (sizeof($snaps) > $this->snapToKeep) {
                    echo "More than " . $this->snapToKeep . " deleting some snapshots...\n";
                    for ($i = $this->snapToKeep; $i < sizeof($snaps); $i++) {
                        echo "Deleting snapshot " . $snaps[$i][1] . "\n";
                        exec($this->ec2ToolsPath . 'ec2-delete-snapshot ' . $snaps[$i][1] . ' -K ' . $this->key . ' -C ' . $this->cert);
                    }
                } else {
                    echo "Not deleting any snapshots. Only " . sizeof($snaps) . " for volume " . $this->volume . ".";
                }

            }

            /**
             * Private function to sort a multiarray
             * @param $multiArray
             * @param $sortValue
             * @return array
             */
            private function mySort($multiArray, $sortValue) {
                $i = 0;
                foreach ($multiArray as $smallArray) {
                    $dates[$i] = $smallArray[$sortValue];
                    $i++;
                }

                arsort($dates);

                //Make new array with sorted dates and values
                $sortedArray = array();
                $counter = 0;
                foreach ($dates as $dKey => $dValue) {
                    $sortedArray[$counter][0] = $dValue;
                    $sortedArray[$counter][1] = $multiArray[$dKey][1];
                    $counter++;
                }

                return $sortedArray;
            }
        }
    ?>

