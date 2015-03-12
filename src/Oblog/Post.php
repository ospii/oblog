<?php
namespace Oblog;

class Post
{
    /**
     * @var string Markdown source.
     */
    private $md;

    /**
     * @var string Markdown converted into html.
     */
    private $html;

    /**
     * @var string First line of source file.
     */
    private $title;

    /**
     * @var int Source md file mtime.
     */
    private $modifiedAt;

    /**
     * @var bool Is this post public.
     */
    private $isPublic = true;

    /**
     *  if this is found in first line of markdown file, this is flagged as not public.
     */
    const DRAFT_STRING = '+DRAFT+';

    /**
     *
     *
     * @param $filename Markdown file to use as source
     */
    public function __construct($filename)
    {
        $this->filename = $filename;
        $this->md = file_get_contents($this->filename);
        $this->modifiedAt = filemtime($this->filename);
        $this->title = strtok($this->md, "\n");

        if (strpos($this->title, self::DRAFT_STRING) !== false) {
            $this->isPublic = false;
        }

    }

    /**
     * Is post public.
     *
     * @return bool Is post public.
     */
    public function isPublic()
    {
        return $this->isPublic;
    }

    /**
     * Get title.
     *
     * @return string Title of the string.
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get html of the post.
     *
     * @return string Html
     */
    public function getHtml()
    {
        if ($this->html === null) {
            $markDowner = new \Michelf\MarkdownExtra();
            $this->html = $markDowner->transform($this->md);
        }
        return $this->html;
    }

    /**
     * Mtime of source file.
     *
     * @return int
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * Get browser friendly filename.
     *
     * @return string
     */
    public function getHtmlFilename()
    {
        $filename = $this->title;
        $filename = mb_strtolower($filename, 'UTF-8');
        $filename = iconv('utf-8','US-ASCII//TRANSLIT//IGNORE', $filename);
        $filename = preg_replace('/[^\p{L}\d]/u', '-', $filename);
        $filename = preg_replace('/-{2,}/', '-', $filename);
        if ($this->isPublic === false) {
            $filename .= '-' . sha1($this->title);
        }
        return $filename . '.html';
    }
}
