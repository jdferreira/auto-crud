<?php

namespace Tests\Unit;

use Exception;
use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Ferreira\AutoCrud\AssertsField;
use Illuminate\Support\Facades\Route;

class AssertsFieldTest extends TestCase
{
    use AssertsField;

    /**
     * @test
     * @testdox It requires that the method `beginAssertFields` is called before `assertField`
     */
    public function it_requires_a_specific_call_order()
    {
        $this->assertException(Exception::class, function () {
            $this->assertField('field');
        });

        $this->beginAssertFields('POST', '/some-route', []);
        $this->assertField('some-field');
    }

    /** @test */
    public function it_can_test_that_the_validation_rules_are_called()
    {
        Route::post('/some-route', function (Request $request) {
            $request->validate([
                'field1' => 'required|regex:/123/',
                'field2' => 'nullable|date_format:Y-m-d',
                'field3' => 'nullable|boolean',
            ]);
        });

        $this->beginAssertFields('POST', '/some-route', [
            'field1' => '123',
            'field2' => null,
            'field3' => null,
        ]);

        // We want to be able to detect it
        $this->assertField('field1')
            ->accepts('123')
            ->rejects('456')
            ->rejects(null);

        $this->assertField('field2')
            ->accepts('2020-01-01')
            ->rejects('not-a-date')
            ->accepts(null);

        $this->assertField('field3')
            ->accepts(true)
            ->accepts(false)
            ->accepts('1')
            ->rejects('on')
            ->accepts(null);
    }
}
