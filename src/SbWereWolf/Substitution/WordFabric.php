<?php

namespace SbWereWolf\Substitution;

class WordFabric
{
    private string $symbols = '';
    private string $digits = '';

    public function __construct(private readonly string $word)
    {
    }

    private function convertToSymbols(): WordFabric
    {
        $this->symbols = mb_strtolower($this->word);
        return $this;
    }

    private function convertToDigits(): WordFabric
    {
        foreach (mb_str_split($this->symbols) as $symbol) {
            switch ($symbol) {
                case 'с':
                case 'з':
                case 'ц':
                    $this->digits .= '0';
                    break;
                case 'д':
                case 'т':
                    $this->digits .= '1';
                    break;
                case 'н':
                    $this->digits .= '2';
                    break;
                case 'м':
                    $this->digits .= '3';
                    break;
                case 'р':
                    $this->digits .= '4';
                    break;
                case 'л':
                    $this->digits .= '5';
                    break;
                case 'ч':
                case 'ш':
                case 'щ':
                case 'ж':
                    $this->digits .= '6';
                    break;
                case 'г':
                case 'к':
                case 'х':
                    $this->digits .= '7';
                    break;
                case 'в':
                case 'ф':
                    $this->digits .= '8';
                    break;
                case 'б':
                case 'п':
                    $this->digits .= '9';
                    break;
            }
        }
        return $this;
    }

    public function make(): Word
    {
        $this->convertToSymbols()
            ->convertToDigits();

        $word = new Word(
            $this->word,
            $this->digits,
        );

        return $word;
    }
}