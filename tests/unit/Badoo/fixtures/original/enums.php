<?php

interface HasColor
{
    public function color(): string;
}

enum Status implements HasColor
{
    // comment will be removed
    case DRAFT;

    /**
     * But this one will stay
     */
    case PUBLISHED;
    case ARCHIVED;

    public static function getDefault(): self
    {
        return self::DRAFT;
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'grey',
            Status::PUBLISHED => 'green',
            Status::ARCHIVED => 'red',
        };
    }
}

enum StatusWithValue: string implements HasColor
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function color(): string
    {
        return 'blue';
    }
}

enum StatusWithNumber: int
{
    case DRAFT = 1;
    case PUBLISHED = 2;
    case ARCHIVED = 3;
}

class BlogPost
{
    private string $color;

    public function __construct(public ?Status $status = null)
    {
        $this->color = ($this->status ?? Status::getDefault())->color();
    }
}

class MainTest
{
    private BlogPost $blogPost;

    public function __construct()
    {
        $this->blogPost = new BlogPost(Status::DRAFT);
    }
}
