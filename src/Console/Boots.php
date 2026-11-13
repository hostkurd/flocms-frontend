<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Console;

use FloCMS\Frontend\Frontend;

/** Commands that read the site's settings: boot the project as a page would. */
trait Boots
{
    private function bootSite(): void
    {
        $project = $this->project();
        try {
            $project->boot();
        } catch (\Throwable $error) {
            $this->warn('The project could not be booted completely: ' . $error->getMessage());
        }
        Frontend::setRoot($project->root());
    }
}
