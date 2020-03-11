<?php


namespace Ipedis\Rabbit\Workflow\Event;


interface BindableEventInterface
{
    const WORKFLOW_START = 'workflow.start';
    const WORKFLOW_FINISH = 'workflow.finish';
    const WORKFLOW_FAILURE = 'workflow.failure';

    const WORKFLOW_ALLOW_TYPES = [self::WORKFLOW_START, self::WORKFLOW_FINISH, self::WORKFLOW_FAILURE];

    const GROUP_START = 'group.start';
    const GROUP_FINISH = 'group.finish';
    const GROUP_FAILURE = 'group.failure';
    const GROUP_SUCCESS = 'group.success';

    const GROUP_ALLOW_TYPES = [self::GROUP_START, self::GROUP_FINISH, self::GROUP_FAILURE, self::GROUP_SUCCESS];

    const TASK_START  = 'task.start';
    const TASK_PROGRESS = 'task.progress';
    const TASK_FAILURE = 'task.failure';
    const TASK_SUCCESS = 'task.success';
    const TASK_FINISH = 'task.finish';

    const TASK_ALLOW_TYPES = [self::TASK_START, self::TASK_PROGRESS, self::TASK_FAILURE, self::TASK_SUCCESS, self::TASK_FINISH];
}
