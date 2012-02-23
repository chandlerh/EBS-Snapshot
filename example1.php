<?php

    require_once "EbsSnapshot.php";
    putenv("EC2_URL=https://ec2.us-west-2.amazonaws.com");
    putenv("EC2_HOME=/PATHTO/ec2-api-tools-1.5.2.4/");
    putenv("JAVA_HOME=/usr");

    $ebsSnapshot = new EbsSnapshot('/home/USER/ec2-api-tools-1.5.2.4/bin/',
        '/home/USER/.ec2/cert-xxxxxxxxxxxxx.pem',
        '/home/USER/.ec2/pk-xxxxxxxxxxxxxxx.pem',
        'my_database_backup_' . date("Y-m-d"),
        '10.0.0.1',
        'databasename',
        'databaseuser',
        'databasepassword',
        'vol-xxxxxxx',
        10);

    $ebsSnapshot->run();

?>
