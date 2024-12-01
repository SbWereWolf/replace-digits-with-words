<?php

namespace SbWereWolf\Substitution;

use PDO;

class Carrier
{
    public function __construct(
        readonly private PDO $db,
        readonly private array $words,
    )
    {
    }

    public function unload()
    {
        $query = $this->db->prepare(
            '
INSERT INTO translation ( original, symbols, digits, length, number)
VALUES(:original,:symbols,:digits,:length,:number)
'
        );

        $original = '';
        $symbols = '';
        $length = 0;
        $digits = '';
        $number = 0;
        $query->bindParam(':original', $original, PDO::PARAM_STR);
        $query->bindParam(':symbols', $symbols, PDO::PARAM_STR);
        $query->bindParam(':digits', $digits, PDO::PARAM_STR);
        $query->bindParam(':length', $length, PDO::PARAM_INT);
        $query->bindParam(':number', $number, PDO::PARAM_INT);

        $rowsRead = 0;
        $rowsInserted = 0;
        foreach ($this->words as $word) {
            if ($word !== '') {
                $rowsRead++;

                $message = "Prepare `$word`";
                yield $message;

                $prepared = (new WordFabric($word))->make();

                $original = $prepared->original();
                $symbols = $prepared->symbols();
                $length = $prepared->length();
                $digits = $prepared->digits();
                $number = $prepared->number();

                $isSuccess = $query->execute();
                if ($isSuccess) {
                    $rowsInserted++;
                }

                $message =
                    "Equipped with `{$original}` `{$symbols}`"
                    . " `{$digits}` `{$length}` `{$number}`";
                yield $message;
            }
        }

        $read = number_format($rowsRead, 0, ',', ' ');
        $inserted = number_format($rowsInserted, 0, ',', ' ');
        $scriptMaxMem =
            round(memory_get_peak_usage(true) / 1024 / 1024, 1);

        $message =
            "Rows read `$read`," .
            "Rows inserted `$inserted`," .
            " max mem allocated is `$scriptMaxMem`Mb";
        yield $message;
    }
}