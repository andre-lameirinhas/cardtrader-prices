<?php

declare(strict_types=1);

namespace App\Tests\Card;

use App\Card\Card;
use App\Card\CardFileReader;
use App\Card\CardVariant;
use App\Card\InvalidCardFileException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CardFileReaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/card-reader-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->dir}/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function write(string $file, string $contents): string
    {
        $path = "{$this->dir}/{$file}";
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @param array<string, mixed> $card
     */
    private function writeCard(string $file, array $card): string
    {
        return $this->write($file, (string) json_encode($card));
    }

    public function testReadsCardWithVariants(): void
    {
        $path = $this->writeCard('001.card.json', [
            'id' => 49946,
            'number' => '001/086',
            'name' => 'Snivy',
            'variants' => [['id' => 95805, 'type' => 'Normal'], ['id' => '95806', 'type' => 'Reverse Holo']],
        ]);

        $card = (new CardFileReader())->readFile($path);

        $this->assertEquals(
            new Card('49946', '001/086', 'Snivy', [new CardVariant('95805', 'Normal'), new CardVariant('95806', 'Reverse Holo')]),
            $card,
        );
        $this->assertSame('001', $card->collectorNumber());
    }

    public function testMissingVariantsDefaultsToEmptyList(): void
    {
        $path = $this->writeCard('001.card.json', ['id' => '1', 'number' => '001/086', 'name' => 'Snivy']);

        $this->assertSame([], (new CardFileReader())->readFile($path)->variants);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'invalid JSON' => ['{not json'];
        yield 'not an object' => ['"Snivy"'];
        yield 'missing id' => ['{"number": "001/086", "name": "Snivy"}'];
        yield 'missing number' => ['{"id": "1", "name": "Snivy"}'];
        yield 'missing name' => ['{"id": "1", "number": "001/086"}'];
        yield 'variants not a list' => ['{"id": "1", "number": "001/086", "name": "Snivy", "variants": "Normal"}'];
        yield 'variant missing type' => ['{"id": "1", "number": "001/086", "name": "Snivy", "variants": [{"id": "2"}]}'];
        yield 'variant missing id' => ['{"id": "1", "number": "001/086", "name": "Snivy", "variants": [{"type": "Normal"}]}'];
    }

    #[DataProvider('invalidFiles')]
    public function testRejectsInvalidFile(string $contents): void
    {
        $path = $this->write('bad.card.json', $contents);

        $this->expectException(InvalidCardFileException::class);
        $this->expectExceptionMessage('bad.card.json');

        (new CardFileReader())->readFile($path);
    }

    public function testRejectsMissingFile(): void
    {
        $this->expectException(InvalidCardFileException::class);

        (new CardFileReader())->readFile("{$this->dir}/missing.card.json");
    }

    public function testReadsDirectorySortedAndSkipsInvalidFiles(): void
    {
        $this->writeCard('087.card.json', ['id' => '87', 'number' => '087/086', 'name' => 'Snivy']);
        $this->writeCard('001.card.json', ['id' => '1', 'number' => '001/086', 'name' => 'Snivy']);
        $this->writeCard('002.json', ['id' => '2', 'number' => '002/086', 'name' => 'Servine']);
        $this->write('050.card.json', '{not json');

        $invalid = [];
        $cards = (new CardFileReader())->readDirectory(
            "{$this->dir}/",
            static function (string $file, InvalidCardFileException $e) use (&$invalid): void {
                $invalid[] = basename($file);
            },
        );

        $this->assertSame(['1', '87'], array_map(static fn (Card $c) => $c->id, $cards));
        $this->assertSame(['050.card.json'], $invalid);
    }

    public function testReadsEmptyDirectory(): void
    {
        $this->assertSame([], (new CardFileReader())->readDirectory($this->dir));
    }
}
