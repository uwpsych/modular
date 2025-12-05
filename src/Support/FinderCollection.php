<?php

namespace InterNACHI\Modular\Support;

use Illuminate\Support\LazyCollection;
use Illuminate\Support\Traits\ForwardsCalls;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * @mixin \Illuminate\Support\LazyCollection
 * @mixin \Symfony\Component\Finder\Finder
 */
class FinderCollection
{
	use ForwardsCalls;
	
	protected const PREFER_COLLECTION_METHODS = ['filter', 'each', 'map'];
	
	public static function forFiles(): self
	{
		return new static(Finder::create()->files());
	}
	
	public static function forDirectories(): self
	{
		return new static(Finder::create()->directories());
	}
	
	public function __construct(
		protected ?Finder $finder = null,
		protected ?LazyCollection $collection = null,
	) {
		if (! $this->finder && ! $this->collection) {
			$this->collection = new LazyCollection();
		}
	}
	
	public function inOrEmpty(string|array $dirs): static
	{
		$dirs = (array) $dirs;

        $expanded = [];

        foreach ($dirs as $dir) {
            if (strpbrk($dir, '*?[]') !== false) {
                $matches = glob($dir, GLOB_ONLYDIR) ?: [];
                $expanded = array_merge($expanded, $matches);
            } else {
                $expanded[] = $dir;
            }
        }

        $existing = array_filter($expanded, static fn ($dir) => is_dir($dir));

        if (empty($existing)) {
            return new static();
        }

        return $this->in($existing);
	}
	
	public function __call($name, $arguments)
	{
		$result = $this->forwardCallTo($this->forwardCallTargetForMethod($name), $name, $arguments);
		
		if ($result instanceof Finder) {
			return new static($result);
		}
		
		if ($result instanceof LazyCollection) {
			return new static($this->finder, $result);
		}
		
		return $result;
	}
	
	protected function forwardCallTargetForMethod(string $name): Finder|LazyCollection
	{
		if (is_callable([$this->finder, $name]) && ! in_array($name, static::PREFER_COLLECTION_METHODS)) {
			return $this->finder;
		}
		
		return $this->forwardCollection();
	}
	
	protected function forwardCollection(): LazyCollection
	{
		return $this->collection ??= new LazyCollection(function() {
			foreach ($this->finder as $key => $value) {
				yield $key => $value;
			}
		});
	}
}
