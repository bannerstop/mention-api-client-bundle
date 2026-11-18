<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle;

use Bannerstop\MentionApiClientBundle\DependencyInjection\Compiler\PsrDefaultsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class BannerstopMentionApiClientBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PsrDefaultsPass());
    }
}
