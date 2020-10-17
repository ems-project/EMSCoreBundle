<?php
namespace EMS\CoreBundle\Tests\Storage;

use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CoreBundle\Service\FileService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FileServiceTest extends WebTestCase
{
    public function testStorageServices(): void
    {
        self::bootKernel();

        $this->assertNotNull(self::$container);

        /**@var FileService $fileService */
        $fileService = self::$container->get('ems.service.file');
        if (!$fileService instanceof FileService) {
            throw new \RuntimeException('FileService not found');
        }

        /**@var StorageInterface $storage*/
        foreach ($fileService->getStorages() as $storage) {
            $this->assertNotNull($storage);

            $this->verifyStorageService($storage);
        }
    }

    private function verifyStorageService(StorageInterface $storage): void
    {

        $this->assertTrue($storage->health());

        $string1 = 'foo';
        $string2 = 'bar';
        $hash = sha1($string1 . $string2);
        if ($storage->head($hash)) {
            $storage->remove($hash);
        }

        $this->assertTrue($storage->initUpload($hash, strlen($string1 . $string2), 'test.bin', 'application/bin'));
        $this->assertTrue($storage->addChunk($hash, $string1));
        $this->assertTrue($storage->addChunk($hash, $string2));
        $this->assertTrue($storage->finalizeUpload($hash));


        $this->assertTrue($storage->head($hash));

        $ctx = \hash_init('sha1');
        $stream = $storage->read($hash);
        $this->assertNotNull($stream);
        while (!$stream->eof()) {
            \hash_update($ctx, $stream->read(8192));
        }
        $computedHash = \hash_final($ctx);

        $this->assertEquals($hash, $computedHash);

        if ($storage->remove($hash)) {
            $this->assertFalse($storage->head($hash));
        }


        $tempFile = \tempnam(sys_get_temp_dir(), 'ems_core_test');
        if (!\is_string($tempFile)) {
            throw new \RuntimeException('Impossible to generate temporary filename');
        }
        $this->assertNotFalse($tempFile !== false);
        $this->assertNotFalse(file_put_contents($tempFile, $string1 . $string2) !== false);
        $this->assertEquals($hash, hash_file('sha1', $tempFile));

        $this->assertTrue($storage->create($hash, $tempFile));
        $this->assertTrue($storage->head($hash));

        $this->assertEquals(strlen($string1 . $string2), $storage->getSize($hash));

        $storage->remove($hash);
        unlink($tempFile);
    }
}
