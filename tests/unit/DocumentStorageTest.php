<?php

use App\Libraries\DocumentStorage;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DocumentStorageTest extends CIUnitTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zeus-documents-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);

        parent::tearDown();
    }

    private function put_file(string $name, string $content = 'x'): void
    {
        file_put_contents($this->directory . DIRECTORY_SEPARATOR . $name, $content);
    }

    public function testPathResolvesAnExistingStoredFile(): void
    {
        $name = str_repeat('ab12', 8);
        $this->put_file($name);

        $this->assertSame($this->directory . DIRECTORY_SEPARATOR . $name, (new DocumentStorage($this->directory))->path($name));
    }

    public function testPathIsNullForAMissingFile(): void
    {
        $this->assertNull((new DocumentStorage($this->directory))->path(str_repeat('ab12', 8)));
    }

    public function testPathRefusesAnythingThatIsNotAStoredName(): void
    {
        $storage = new DocumentStorage($this->directory);
        $this->put_file('secret.txt');

        // Une valeur altérée en base ne doit jamais sortir du dossier des documents.
        foreach (['secret.txt', '../secret.txt', '..\\secret.txt', str_repeat('AB12', 8), str_repeat('ab12', 9), '', null] as $name) {
            $this->assertNull($storage->path($name), var_export($name, true));
        }
    }

    public function testDeleteRemovesTheFileAndIgnoresMissingOnes(): void
    {
        $name = str_repeat('cd34', 8);
        $this->put_file($name);
        $storage = new DocumentStorage($this->directory);

        $storage->delete($name);
        $this->assertFileDoesNotExist($this->directory . DIRECTORY_SEPARATOR . $name);

        // Déjà supprimé, nom invalide, null : sans effet ni erreur.
        $storage->delete($name);
        $storage->delete('../../autre');
        $storage->delete(null);
        $this->addToAssertionCount(1);
    }

    public function testDeleteNeverTouchesFilesThatAreNotStoredNames(): void
    {
        $this->put_file('keep.txt');

        (new DocumentStorage($this->directory))->delete('keep.txt');

        $this->assertFileExists($this->directory . DIRECTORY_SEPARATOR . 'keep.txt');
    }
}
