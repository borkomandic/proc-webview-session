<?php declare(strict_types=1);

namespace ProCoders\WebViewSession;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class ProcWebViewSession extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('controllers.xml');
        $loader->load('services.xml');
        $loader->load('subscribers.xml');
        parent::build($container);
    }
}
