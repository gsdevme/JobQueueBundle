<?php

namespace Markup\JobQueueBundle\Publisher;

use Markup\JobQueueBundle\Exception\MissingTopicException;
use Markup\JobQueueBundle\Exception\UndefinedProducerException;
use Markup\JobQueueBundle\Model\Job;
use Symfony\Component\DependencyInjection\ContainerAware;

/**
 * Delegates production of jobs to oldsound component
 */
class JobPublisher extends ContainerAware
{
    public function publish(Job $job)
    {
        $job->validate();
        $topic = str_replace('-', '_', $job->getTopic());
        if (!$topic) {
            throw new MissingTopicException('A job must have a topic to allow it to be published');
        }

        // ensure rabbit mq producer exists by convention of topic - throw exception if not
        $fqProducerName = sprintf('old_sound_rabbit_mq.%s_producer', $topic);
        if (!$this->container->has($fqProducerName)) {
            throw new UndefinedProducerException(sprintf("Producer for topic '%s' has not been configured", $topic));
        }
        // add the 'class' of the job as an argument to allow it to be constructed again by consumer
        $message = array_merge($job->getArgs(), ['job_class' => get_class($job)]);
        $producer = $this->container->get($fqProducerName);
        $producer->setContentType('application/json');
        $producer->publish(json_encode($message));
    }
}