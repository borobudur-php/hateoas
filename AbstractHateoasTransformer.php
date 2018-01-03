<?php
/**
 * This file is part of the Borobudur package.
 *
 * (c) 2018 Borobudur <http://borobudur.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Borobudur\Component\Hateoas;

use Borobudur\Component\Transformer\TransformerInterface;
use Borobudur\Component\Transformer\MutatorTrait;
use InvalidArgumentException;

/**
 * @author  Iqbal Maulana <iq.bluejack@gmail.com>
 */
abstract class AbstractHateoasTransformer implements TransformerInterface
{
    use MutatorTrait;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var NormalizerInterface
     */
    private $normalizer;

    public function __construct(RouterInterface $router, NormalizerInterface $normalizer)
    {
        $this->router = $router;
        $this->normalizer = $normalizer;
    }

    public function transform($data, array $context = []): array
    {
        $normalized = $this->normalizer->normalize($data, $context);
        $normalized = $this->mutate($normalized);

        $this->build($data, $normalized);

        return $normalized;
    }

    protected function build($data, array &$normalized): void
    {
        $links = [];
        $embedded = [];

        foreach ($this->links($data) as $name => $link) {
            if (null === $this->get($link, 'route')) {
                throw new InvalidArgumentException(
                    sprintf('Missing "route" param for link "%s"', $name)
                );
            }

            if (null === $this->get($link['route'], 'name')) {
                throw new InvalidArgumentException(
                    sprintf('Missing "route.name" param for link "%s"', $name)
                );
            }

            $href = $this->generate($link['route']['name'], $this->get($link['route'], 'params', []));
            $methods = $this->router->getMethods($link['route']['name']);
            unset($link['route']);

            if (true === $this->get($link, 'embedded', false)) {
                if (array_key_exists($name, $normalized)) {
                    $embedded[$name] = $normalized[$name];
                    unset($normalized[$name]);
                }

                unset($link['embedded']);
            }

            $links[] = array_merge(['href' => $href, 'rel' => $name, 'methods' => $methods], $link);
        }

        $normalized['_links'] = $links;

        if (!empty($embedded)) {
            $normalized['_embedded'] = $embedded;
        }
    }

    protected function generate(string $name, array $params = []): string
    {
        return $this->router->generate($name, $params);
    }

    protected function get(array $arr, string $key, $default = null)
    {
        return array_key_exists($key, $arr) ? $arr[$key] : $default;
    }

    abstract protected function links($data): array;
}
