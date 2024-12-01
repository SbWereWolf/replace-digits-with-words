<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use SbWereWolf\Scripting\Config\EnvReader;
use SbWereWolf\Scripting\Convert\NanosecondsConverter;
use SbWereWolf\Scripting\FileSystem\Path;
use SbWereWolf\Substitution\Carrier;

$message = date(DATE_ATOM) . ': Script is starting';
echo $message . PHP_EOL;
$messageBuffer[] = $message;

$startMoment = hrtime(true);

$digitsInput = '';
if (isset($argv[1])) {
    $digitsInput = $argv[1];
}
if ($digitsInput === '') {
    $message = date(DATE_ATOM) . ': Digits input is empty';
    echo $message . PHP_EOL;
    $messageBuffer[] = $message;
}

if ($digitsInput !== '') {
    $message = date(DATE_ATOM) . ": Digits input is `$digitsInput`";
    echo $message . PHP_EOL;
    $messageBuffer[] = $message;
}

$wordsInput = '';
if (isset($argv[2])) {
    $wordsInput = $argv[2];
}
if ($wordsInput === '') {
    $message = date(DATE_ATOM) . ': Words input is empty';
    echo $message . PHP_EOL;
    $messageBuffer[] = $message;
}

if ($wordsInput !== '') {
    $message = date(DATE_ATOM) . ": Words input is `$wordsInput`";
    echo $message . PHP_EOL;
    $messageBuffer[] = $message;
}

$pathParts = [__DIR__, 'vendor', 'autoload.php'];
$autoloaderPath = join(DIRECTORY_SEPARATOR, $pathParts);
require_once($autoloaderPath);

$logger = new Logger('string');

$pathComposer = new Path(__DIR__);
$logsPath = $pathComposer->make(
    [
        'logs',
        pathinfo(__FILE__, PATHINFO_FILENAME) . '-' . time() . '.log',
    ]
);

$writeHandler = new StreamHandler($logsPath);
$logger->pushHandler($writeHandler);

$logger->pushProcessor(function ($record) {
    /** @var LogRecord $record */
    echo "{$record->datetime} {$record->message}" . PHP_EOL;

    return $record;
});

foreach ($messageBuffer as $message) {
    $logger->notice($message);
}

$message = 'Run starting';
$logger->notice($message);

$configPath = $pathComposer->make(['.env']);
(new EnvReader($configPath))->defineConstants();

$db = (new PDO(
    constant('DSN'),
    constant('LOGIN'),
    constant('PASSWORD'),
));

$message = 'Use DSN `'
    . constant('DSN')
    . '`, login `'
    . constant('LOGIN')
    . '`, password `'
    . constant('PASSWORD')
    . '`';
$logger->notice($message);

$db->exec('
DROP TABLE IF EXISTS translation;
CREATE TABLE IF NOT EXISTS translation
    (
        id INTEGER
            CONSTRAINT translation_pk
                PRIMARY KEY,
        original TEXT NOT NULL,
        symbols TEXT,
        free integer DEFAULT 1,
        digits TEXT,
        length integer,
        number integer
    );
CREATE INDEX IF NOT EXISTS translation_free_number_length_index 
    ON translation (free DESC, number DESC, length ASC)
');

$db->beginTransaction();

$words = explode(',', $wordsInput);
$importer = new Carrier($db, $words);
foreach ($importer->unload() as $message) {
    $logger->notice($message);
}


$message = 'Prepare SQL statement';
$logger->notice($message);

$markAsNotFree = $db->prepare(
    'UPDATE translation SET free = 0 WHERE id = :id'
);
$id = 0;
$markAsNotFree->bindParam(':id', $id, PDO::PARAM_INT);

$obtainFreeLength = $db->prepare(
    '
SELECT length
  FROM translation
 WHERE free = 1 AND length<=:length
 GROUP BY length
ORDER BY length DESC
'
);
$lengthLimit = 0;
$obtainFreeLength->bindParam(':length', $lengthLimit, PDO::PARAM_INT);

$obtainFreeWords = $db->prepare(
    '
SELECT id, original, length, digits, number
  FROM translation
 WHERE free = 1 AND length=:length
 ORDER BY free DESC, number DESC, length ASC
'
);
$length = 0;
$obtainFreeWords->bindParam(':length', $length, PDO::PARAM_INT);

$stepsNumber = 0;
$swapsNumber = 0;


$inputLength = mb_strlen($digitsInput);
$start = 0;
$lengthLimit = $inputLength - $start;

$message = "Input string length is `{$inputLength}`,";
$logger->notice($message);

$output = '';
while ($lengthLimit !== 0) {
    $message = "Step #`{$stepsNumber}`,"
        . "starting search at `{$start}`"
        . " length limit is `{$lengthLimit}`";
    $logger->notice($message);

    $obtainFreeLength->execute();

    while (
    $freeLength = $obtainFreeLength->fetch(PDO::FETCH_ASSOC)
    ) {
        $letNewSearch = false;

        $length = $freeLength['length'];

        $message = "Free length is `$length`";
        $logger->notice($message);


        $obtainFreeWords->execute();
        while (
        $freeWords = $obtainFreeWords->fetch(PDO::FETCH_ASSOC)
        ) {
            $digits = $freeWords['digits'];

            $message = "Find with digits `$digits`";
            $logger->notice($message);

            $sample = substr($digitsInput, $start, $length);
            if ($digits === $sample) {
                $original = $freeWords['original'];
                $message =
                    "Found `$sample` of length `$length`"
                    . " at position `$start`"
                    . ", swap it with `$original`";
                $logger->notice($message);

                $id = $freeWords['id'];
                $markAsNotFree->execute();
                $swapsNumber++;

                $message = "Swaps #`{$swapsNumber}`";
                $logger->notice($message);

                $original = $freeWords['original'];
                $output .= PHP_EOL . "`$digits` `$original`";
                $message = "Output string is `$output`";
                $logger->notice($message);

                $start += $length;
                $stepsNumber++;

                $message = "Move search position to `$start`"
                    . ", next step #`{$stepsNumber}`";
                $logger->notice($message);

                $letNewSearch = true;
                break;
            }

            $message = "Search with digits `$digits` was not found";
            $logger->notice($message);
        }

        if ($letNewSearch) {
            break;
        }
        $message = "Search with length `$length` was finished`";
        $logger->notice($message);
    }

    if ($lengthLimit === $inputLength - $start) {
        $sample = substr($digitsInput, $start, 1);
        $output .= $sample;

        $output .= PHP_EOL . "`$sample`";
        $message = "Output string is `$output`";
        $logger->notice($message);

        $start++;
        $stepsNumber++;

        $message = "Move search position by on symbol to `$start`"
            . ", next step #`{$stepsNumber}`";
        $logger->notice($message);
    }

    $lengthLimit = $inputLength - $start;
}

$db->rollBack();

$steps = number_format($stepsNumber, 0, ',', ' ');
$swaps = number_format($swapsNumber, 0, ',', ' ');
$scriptMaxMem =
    round(memory_get_peak_usage(true) / 1024 / 1024, 1);

$message =
    "Steps number is `$steps`,"
    . "Swaps number is `$swaps`,"
    . " max mem allocated is `$scriptMaxMem`Mb";
$logger->notice($message);

$message =
    "Run result is `$output`";
$logger->notice($message);

$finishMoment = hrtime(true);

$totalTime = $finishMoment - $startMoment;
$timeParts = new NanosecondsConverter();
$printout = $timeParts->print($totalTime);

$message = "Run duration is $printout";
$logger->notice($message);

$message = 'Script is finished';
$logger->notice($message);

$logger->close();
