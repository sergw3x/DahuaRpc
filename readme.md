# USAGE

```php
require_once "DahuaRpc.php";

$host = "192.168.4.5";
$login = "admin";
$passwd = "admin"

try {

    $dahua = new DahuaRpc($host, $login, $passwd);
    $dahua->login();

    $time = $dahua->get_current_time();
    $currentTime = $dahua->get_current_time();
    echo "Current device time: " . $currentTime . "\n";

    // Getting people counting statistics
    $peopleCountInfo = $dahua->get_people_counting_info();
    echo "People counting info: " . print_r($peopleCountInfo, true) . "\n"; 

    // Start searching for statistical data for a period
    $objectId  = $peopleCountInfo;       // returns from get_people_counting_info
    $startTime = "2025-07-30 00:00:00";
    $endTime   = "2025-07-30 23:59:59";
    $areaID    = 1;                      // your AreaID

    $totalCount = $dahua->start_find_statistics_data($objectId, $startTime, $endTime, $areaID);
    echo "Total count: " . $totalCount . "\n"; // array of arrays. each array contains counts for a certain period of time

    // getting statistics
    $stats = $dahua->do_find_statistics_data($objectId);

    // stop searching
    $dahua->stop_find_statistics_data($objectId);

    $dahua->logout();

    $EnteredSubtotal = 0;
    $ExitedSubtotal  = 0;
    $PassedSubtotal  = 0;
    foreach ($stats as $stat) {
        $EnteredSubtotal += $stat['EnteredSubtotal'];
        $ExitedSubtotal  += $stat['ExitedSubtotal'];
        $PassedSubtotal  += $stat['PassedSubtotal'];
    }

    echo "EnteredSubtotal: $EnteredSubtotal\n".
        "ExitedSubtotal: $ExitedSubtotal\n".
        "PassedSubtotal: $PassedSubtotal\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```