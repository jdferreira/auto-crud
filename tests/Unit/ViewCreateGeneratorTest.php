<?php

namespace Tests\Unit;

use DOMNode;
use DOMXPath;
use DOMDocument;
use Tests\TestCase;
use Ferreira\AutoCrud\AssertsHTML;
use Ferreira\AutoCrud\Generators\ViewCreateGenerator;

class ViewCreateGeneratorTest extends TestCase
{
    use AssertsHTML;

    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param string $table
     *
     * @return ViewCreateGenerator
     */
    private function generator(string $table): ViewCreateGenerator
    {
        return app(ViewCreateGenerator::class, [
            'table' => $table,
        ]);
    }

    private function getDOMDocument(string $table): DOMDocument
    {
        $doc = new DOMDocument();

        $doc->loadHTML($this->generator($table)->generate());

        return $doc;
    }

    /** @test */
    public function it_can_generate_a_view()
    {
        $this->generator('users')->save();

        $this->assertFileExists(resource_path('views/users/create.blade.php'));
    }

    /** @test */
    public function it_shows_a_form()
    {
        $doc = $this->getDOMDocument('users');

        $this->assertHTML("//form[@method='POST']", $doc);
    }

    /** @test */
    public function it_is_titled_based_on_the_model_name()
    {
        $doc = $this->getDOMDocument('users');
        $this->assertHTML("//h1[text()='New user']", $doc);

        $doc = $this->getDOMDocument('payment_methods');
        $this->assertHTML("//h1[text()='New payment method']", $doc);
    }

    /** @test */
    public function it_contains_a_label_for_each_field()
    {
        $doc = $this->getDOMDocument('users');

        $this->assertHTML("//label[@for='name' and text()='Name']", $doc);
        $this->assertHTML("//label[@for='email' and text()='Email']", $doc);
        $this->assertHTML("//label[@for='subscribed' and text()='Subscribed']", $doc);
        $this->assertHTML("//label[@for='birthday' and text()='Birthday']", $doc);
        $this->assertHTML("//label[@for='wake-up' and text()='Wake up']", $doc);
    }

    /** @test */
    public function it_converts_names_to_kebab_case()
    {
        $doc = $this->getDOMDocument('users');

        $this->assertHTML("//label[@for='wake-up' and text()='Wake up']", $doc);
    }

    /** @test */
    public function it_renders_input_fields_according_to_field_type()
    {
        // NOTICE: I'm not testing for
        // - <input type="number"> because this is broken and doesn't work in
        //   some browsers; we'll render it as a regular type="text" input
        // - <input type="password"> because CRUD applications have no need for
        //   a password field
        // - <input type="tel"> because I don't have a test for it; but I should
        //   add one in the future! TODO: Add this.

        $doc = $this->getDOMDocument('users');
        $this->assertHTML("//input[@name='name' and @type='text']", $doc);
        $this->assertHTML("//input[@name='email' and @type='email']", $doc);
        $this->assertHTML("//input[@name='subscribed' and @type='checkbox']", $doc);
        $this->assertHTML("//input[@name='birthday' and @type='date']", $doc);
        $this->assertHTML("//input[@name='wake-up' and @type='time']", $doc);

        $doc = $this->getDOMDocument('avatars');
        $this->assertHTML("//input[@name='user-id' and @type='text']", $doc);
        $this->assertHTML("//input[@name='data' and @type='file']", $doc);

        $doc = $this->getDOMDocument('products');
        $this->assertHTML("//select[@name='type']", $doc);

        $doc = $this->getDOMDocument('payment_methods');
        $this->assertHTML("//textarea[@name='primary']", $doc);
    }

    /** @test */
    public function it_offers_the_valid_values_for_enum_fields()
    {
        $doc = $this->getDOMDocument('products');
        $xpath = new DOMXPath($doc);
        $node = $xpath->query("//select[@name='type']")->item(0);

        $childNodes = collect($node->childNodes)->filter(function (DOMNode $node) {
            return $node->nodeName !== '#text';
        })->values();

        $typesOfChildren = $childNodes->map(function (DOMNode $node) {
            return $node->nodeName;
        })->unique()->all();

        $options = $childNodes->mapWithKeys(function (DOMNode $node) {
            $key = $node->attributes->getNamedItem('value')->nodeValue;
            $value = $node->textContent;

            return [$key => $value];
        })->all();

        $this->assertEquals(['option'], $typesOfChildren);

        $this->assertEquals([
            'food' => 'Food',
            'stationery' => 'Stationery',
            'other' => 'Other',
        ], $options);
    }

    /** @test */
    public function it_renders_numeric_fields_as_inputs_of_type_text()
    {
        $doc = $this->getDOMDocument('avatars');
        $this->assertHTML("//input[@name='user-id' and @type='text']", $doc);
    }

    /** @test */
    public function it_renders_text_fields_named_email_as_inputs_of_type_email()
    {
        $doc = $this->getDOMDocument('users');
        $this->assertHTML("//input[@name='email' and @type='email']", $doc);
    }

    /** @test */
    public function it_renders_a_submit_button()
    {
        $doc = $this->getDOMDocument('users');
        $this->assertHTML("//button[@type='submit' and text()='Submit']", $doc);
    }

    /** @test */
    public function it_marks_required_input_fields_as_such()
    {
        $doc = $this->getDOMDocument('users');
        $this->assertHTML("//input[@name='name' and @required]", $doc);
    }
}
