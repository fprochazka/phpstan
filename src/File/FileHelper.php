<?php declare(strict_types = 1);

namespace PHPStan\File;

class FileHelper
{

	/** @var string */
	private $workingDirectory;

	public function __construct(string $workingDirectory)
	{
		$this->workingDirectory = $this->normalizePath($workingDirectory);
	}

	public function getWorkingDirectory(): string
	{
		return $this->workingDirectory;
	}

	public function absolutizePath(string $path): string
	{
		if (DIRECTORY_SEPARATOR === '/') {
			if (substr($path, 0, 1) === '/') {
				return $path;
			}
		} else {
			if (substr($path, 1, 1) === ':') {
				return $path;
			}
		}

		return rtrim($this->getWorkingDirectory(), '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
	}

	public function normalizePath(string $originalPath): string
	{
		list($scheme, $path) = $this->parsePath($originalPath);

		$path = str_replace('\\', '/', $path);
		$path = preg_replace('~/{2,}~', '/', $path);

		$pathRoot = strpos($path, '/') === 0 ? DIRECTORY_SEPARATOR : '';
		$pathParts = explode('/', trim($path, '/'));

		$normalizedPathParts = [];
		foreach ($pathParts as $pathPart) {
			if ($pathPart === '.') {
				continue;
			}
			if ($pathPart === '..') {
				$removedPart = array_pop($normalizedPathParts);
				if ($scheme === 'phar' && substr($removedPart, -5) === '.phar') {
					$scheme = null;
				}
			} else {
				$normalizedPathParts[] = $pathPart;
			}
		}

		return ($scheme !== null ? $scheme . '://' : '') . $pathRoot . implode(DIRECTORY_SEPARATOR, $normalizedPathParts);
	}

	public function resolveTempDir(string $rootDir): string
	{
		$tmpDir = getenv('PHPSTAN_TEMP_DIR');
		if (!empty($tmpDir)) {
			if (!is_dir($tmpDir) || !is_writable($tmpDir)) {
				throw new \RuntimeException(sprintf('Directory %s provided in PHPSTAN_TEMP_DIR is not writable.', $tmpDir));
			}
			return $tmpDir;
		}

		$tmpDir = $rootDir . '/tmp';
		list($scheme,) = $this->parsePath($tmpDir);
		if ($scheme !== 'phar') {
			return $tmpDir;
		}

		$tmpDir = tempnam(sys_get_temp_dir(), 'phpstan_');
		if (file_exists($tmpDir)) {
			unlink($tmpDir);
		}
		if (!@mkdir($tmpDir) && !is_dir($tmpDir)) {
			throw new \RuntimeException(sprintf('Cannot create a temp directory in system path %s', $tmpDir));
		}

		return $tmpDir;
	}

	private function parsePath(string $path): array
	{
		if (preg_match('~^([a-z]+)\\:\\/\\/(.+)~', $path, $m)) {
			return [$m[1], $m[2]];
		}

		return [null, $path];
	}

}
