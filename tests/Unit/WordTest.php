<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Word;

class WordTest extends TestCase
{
    /** @test */
    public function it_does_things()
    {
        $tests = [
            ['class',              'payment_methods', 'PaymentMethod'],
            ['classPlural',        'payment_methods', 'PaymentMethods'],
            ['label',              'payment_method',  'payment method'],
            ['label',              'payment_methods', 'payment methods'],
            ['labelSingular',      'payment_methods', 'payment method'],
            ['labelPlural',        'payment_method',  'payment methods'],
            ['labelUpper',         'payment_method',  'Payment method'],
            ['labelUpper',         'payment_methods', 'Payment methods'],
            ['labelUpperSingular', 'payment_methods', 'Payment method'],
            ['labelUpperPlural',   'payment_method',  'Payment methods'],
            ['snake',              'payment_method',  'payment_method'],
            ['snake',              'payment_methods', 'payment_methods'],
            ['snakeSingular',      'payment_methods', 'payment_method'],
            ['snakePlural',        'payment_method',  'payment_methods'],
            ['kebab',              'payment_method',  'payment-method'],
            ['kebab',              'payment_methods', 'payment-methods'],
            ['kebabSingular',      'payment_methods', 'payment-method'],
            ['kebabPlural',        'payment_method',  'payment-methods'],
            // ['variable',           'payment_method',  'paymentMethod'],
            // ['variable',           'payment_methods', 'paymentMethods'],
            // ['variableSingular',   'payment_methods', 'paymentMethod'],
            // ['variablePlural',     'payment_method',  'paymentMethods'],
            ['method',             'payment_method',  'paymentMethod'],
            ['method',             'payment_methods', 'paymentMethods'],
            ['methodSingular',     'payment_methods', 'paymentMethod'],
            ['methodPlural',       'payment_method',  'paymentMethods'],
        ];

        foreach ($tests as [$method, $input, $expected]) {
            $this->assertEquals(
                $expected,
                Word::$method($input),
                "The method $method produced the wrong result"
            );
        }
    }
}
