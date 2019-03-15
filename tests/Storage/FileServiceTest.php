<?php
namespace EMS\CoreBundle\Tests\Service;

use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CoreBundle\Service\FileService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FileServiceTest extends WebTestCase
{
    public function testStorageServices()
    {
        self::bootKernel();

        $this->assertNotNull(self::$container);

        /**@var FileService $fileService */
        $fileService = self::$container->get('ems.service.file');

        /**@var StorageInterface $storage*/
        foreach ($fileService->getStorages() as $storage) {
            $this->assertNotNull($storage);

            $this->verifyStorageService($storage);
        }
    }

    private function verifyStorageService(StorageInterface $storage)
    {

        $this->assertTrue($storage->health());

        $string1 = 'foo';
        $string2 = 'bar';
        $hash = sha1($string1.$string2);
        if ($storage->head($hash)) {
            $storage->remove($hash);
        }

        $this->assertTrue($storage->initUpload($hash, strlen($string1.$string2), 'test.bin', 'application/bin'));
        $this->assertTrue($storage->addChunk($hash, $string1));
        $this->assertTrue($storage->addChunk($hash, $string2));
        $this->assertTrue($storage->finalizeUpload($hash));


        $this->assertTrue($storage->head($hash));

        $this->assertNotNull($storage->getLastUpdateDate($hash));


        $ctx = hash_init('sha1');
        $handler = $storage->read($hash);
        $this->assertNotFalse($handler);
        while (!feof($handler)) {
            hash_update($ctx, fread($handler, 8192));
        }
        $computedHash = hash_final($ctx);

        $this->assertEquals($hash, $computedHash);

        $contextName = 'test';

        if ($storage->supportCacheStore()) {
            $this->assertTrue($storage->initUpload($hash, strlen($string1.$string2), 'test.bin', 'application/bin', $contextName));
            $this->assertTrue($storage->addChunk($hash, $string1, $contextName));
            $this->assertTrue($storage->addChunk($hash, $string2, $contextName));
            $this->assertTrue($storage->finalizeUpload($hash, $contextName));

            $this->assertTrue($storage->head($hash, $contextName));
            $storage->clearCache();
        }
        if ($storage->remove($hash)) {
            $this->assertFalse($storage->head($hash));
        }
        $this->assertFalse($storage->head($hash, $contextName));


        $tempFile = tempnam(sys_get_temp_dir(), 'ems_core_test');
        $this->assertNotFalse($tempFile);
        $this->assertNotFalse(file_put_contents($tempFile, $string1.$string2));
        $this->assertEquals($hash, hash_file('sha1', $tempFile));

        $this->assertTrue($storage->create($hash, $tempFile));
        $this->assertTrue($storage->head($hash));

        $this->assertEquals(strlen($string1.$string2), $storage->getSize($hash));

        $storage->remove($hash);
        unlink($tempFile);
    }
}
