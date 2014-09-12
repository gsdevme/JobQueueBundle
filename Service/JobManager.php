<?php

namespace Markup\JobQueueBundle\Service;

use BCC\ResqueBundle\Job;
use Markup\JobQueueBundle\Exception\UnknownQueueException;
use Markup\JobQueueBundle\Job\ConsoleCommandJob;
use Symfony\Bundle\FrameworkBundle\Console\Application;

/**
 * Controller for adding jobs to a queue
 */
class JobManager
{
    private $resque;
    private $queues;

    public function __construct($resque)
    {
        $this->resque = $resque;
        $this->queues = [];
    }

    /**
     * Adds a job to the resque queue
     * @param Job $job
     */
    public function addJob(Job $job, $dateTime = null)
    {
        if ($dateTime === null) {
            $this->resque->enqueue($job);

            return;
        }
        $this->resque->enqueueAt($dateTime, $job);
    }

    /**
     * Adds a named command to the job queue
     * @param string  $command     A valid command for this application
     * @param string  $queue       The name of a valid queue
     * @param integer $timeout     The amount of time to allow the command to run
     * @param integer $idleTimeout The amount of idle time to allow the command to run
     */
    public function addCommandJob($command, $queue = 'default', $timeout = 60, $idleTimeout = 60)
    {
        if ($this->isValidQueue($queue) === false) {
            $valid = [];
            foreach ($this->queues as $server => $queues) {
                foreach ($queues as $q) {
                    $valid[] = sprintf('%s-%s', $q, $server);
                }
            }
            throw new UnknownQueueException(
                sprintf('Attempted to add to queue `%s` which is not defined by the application, valid queues are %s', $queue, implode(',', $valid))
            );
        }
        $job = new ConsoleCommandJob();
        $job->setCommand($command);
        $job->setQueue($queue);
        $job->setTimeout($timeout);
        $job->setIdleTimeout($idleTimeout);
        $this->addJob($job);
    }

    /**
     * Adds a named command to the job queue at a specific datetime
     * @param string  $command     A valid command for this application
     * @param string  $dateTime    The DateTime to execute the command
     * @param string  $queue       The name of a valid queue
     * @param integer $timeout     The amount of time to allow the command to run
     * @param integer $idleTimeout The amount of idle time to allow the command to run
     */
    public function addScheduledCommandJob(
        $command,
        \DateTime $dateTime,
        $queue = 'default',
        $timeout = 60,
        $idleTimeout = 60
    ) {
        if ($this->isValidQueue($queue) === false) {
            throw new UnknownQueueException(sprintf('Attempted to add to queue `%s` which is not defined by the application, valid queues are %s', $queue, implode(',', $this->queues)));
        }
        $job = new ConsoleCommandJob();
        $job->setCommand($command);
        $job->setQueue($queue);
        $job->setTimeout($timeout);
        $job->setIdleTimeout($idleTimeout);
        $this->addJob($job, $dateTime);
    }

    public function isValidQueue($queue)
    {
        $valid = [];
        foreach ($this->queues as $server => $queues) {
            foreach ($queues as $q) {
                $valid[] = sprintf('%s-%s', $q, $server);
            }
        }

        return in_array($queue, $valid);
    }

    public function getQueues($server = null)
    {
        if (!$server) {
            return $this->queues;
        }
        if (!array_key_exists($server, $this->queues)) {
            throw new \Exception(sprintf('Queues for server %s do not exist', $server));
        }

        return $this->queues[$server];
    }

    public function setQueues($queues)
    {
        $this->queues = $queues;
    }

    public function getCountOfJobsInQueue($queueName)
    {
        if (!$this->isValidQueue($queueName)) {
            throw new UnknownQueueException(sprintf('Attempted to add get count for queue `%s` which is not defined by the application, valid queues are %s', $queue, implode(',', $this->queues)));
        }

        return $this->resque->getQueue($queueName)->getSize();
    }
}
