<?php
namespace Oblog;

class Blog
{
    /**
     * @var string Source of MD files.
     */
    private $sourcePath;

    /**
     * @var string Output path.
     */
    private $outputPath;

    /**
     * @var string Twig template path
     */
    private $templatePath;

    /**
     * @var string Base url for site.
     */
    private $baseUrl;

    /**
     * @var string Name of the blog/site
     */
    private $name;

    /**
     * @var array Info for atom feed's author section
     */
    private $author = array('name' => null, 'email' => null);

    /**
     * @var string Description for the site
     */
    private $description;

    /**
     * @param $baseUrl Base url for the site eg. http://example.com or http://example.com/blog
     * @return $this
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');

        return $this;
    }

    /**
     * @param $path Path for twig templates
     * @return $this
     */
    public function setTemplatePath($path)
    {
        $this->templatePath = $path;

        return $this;
    }

    /**
     * @param $path Path for markdown files
     * @return $this
     */
    public function setSourcePath($path)
    {
        $this->sourcePath = realpath($path);

        return $this;
    }

    /**
     * @param $path Path for outputfiles eg. document_root. Note! All html files in this directory will be removed!
     * @return $this
     */
    public function setOutputPath($path)
    {
        $this->outputPath = $path;

        return $this;
    }

    /**
     * @param $name Name for the site/blog
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string $name Name of the author
     * @param string $email Email for the author
     * @return $this
     */
    public function setAuthor($name = null, $email = null)
    {
        $this->author['name'] = $name;
        $this->author['email'] = $email;

        return $this;
    }

    /**
     * @param string $description Description for the site
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Generate HTML from source + templates and sitemap.xml into output directory.
     *
     * @return bool
     */
    public function generateStaticPosts()
    {
        if ($this->sourcePath === null || $this->templatePath === null || $this->outputPath === null) {
            throw new \InvalidArgumentException('Check paths');
        }

        $sourceFiles = array();
        foreach (glob($this->sourcePath . '/*.md') as $mdFile) {
            $sourceFiles[] = $mdFile;
        }

        if (count($sourceFiles) === 0) {
            throw new \LengthException('No Markdown files found to crunch.');
        }

        /**
         * Source files prefixed with running number => goodness
         * '1-first-post.md','2-second-post.md' etc.
         */
        natsort($sourceFiles);
        $sourceFiles = array_reverse($sourceFiles);

        $lastPostKey = false;
        $postFilenames = $links = $posts = array();
        foreach ($sourceFiles as $key => $mdFile) {
            $post = new Post($mdFile);

            if ($post->isPublic()) {
                $links[] = array('url' => $post->getHtmlFilename(), 'title' => $post->getTitle());
            }

            if ($lastPostKey === false && $post->isPublic()) {
                $lastPostKey = $key;
            }

            if (array_search($post->getHtmlFilename(), $postFilenames) !== false) {
                throw new \LogicException('Titles resulting in identical filenames found "' . $post->getTitle() . '"');
            }
            $postFilenames[] = $post->getHtmlFilename();

            $posts[$key] = $post;
        }

        foreach (glob($this->outputPath . '/*.html') as $htmlFile) {
           unlink($htmlFile);
        }

        $twigLoader = new \Twig_Loader_Filesystem($this->templatePath);
        $twig = new \Twig_Environment($twigLoader);

        $siteMapUrls = array();
        /* @var $post \Oblog\Post */
        foreach ($posts as $key => $post) {
            $postHtml = $twig->render('post.html', array(
                'title'      => $post->getTitle(),
                'modifiedAt' => $post->getModifiedAt(),
                'post'       => $post->getHtml(),
                'filename'   => $post->getHtmlFilename(),
            ));

            $pageVariables = array(
                'article' => $postHtml,
                'links' => $links,
                'title' => $post->getTitle(),
                'baseUrl' => $this->baseUrl,
                'filename' => $post->getHtmlFilename(),
            );

            if ($key == $lastPostKey) {
                $pageVariables['canonical'] = $this->baseUrl . '/' . $post->getHtmlFilename();
            }

            $pageHtml = $twig->render('page.html', $pageVariables);

            $outputFilename = $post->getHtmlFilename();
            file_put_contents($this->outputPath . '/' . $outputFilename, $pageHtml);
            touch($this->outputPath . '/' . $outputFilename, $post->getModifiedAt());
            if ($key == $lastPostKey) {
                file_put_contents($this->outputPath . '/index.html', $pageHtml);
            }

            if ($post->isPublic()) {
                $siteMapUrls[] = array(
                    'loc' => $this->baseUrl . '/' . $outputFilename,
                    'priority' => 0.5,
                    'lastmod' => date('Y-m-d', $post->getModifiedAt()),
                    'title' => $post->getTitle(),
                    'updated' => date('c', $post->getModifiedAt()),
                    );
            } else {
                echo PHP_EOL . 'DRAFT at ' . $this->baseUrl . '/' . $outputFilename;
            }
        }

        $feedUrls = $siteMapUrls;
        if ($lastPostKey !== false) {
            $siteMapUrls[] = array(
                'loc'        => $this->baseUrl . '/',
                'priority'   => '1',
                'lastmod'    => date('Y-m-d', $posts[$lastPostKey]->getModifiedAt()),
                'changefreq' => 'daily',
             );
        }

        if ($twigLoader->exists('sitemap.xml')) {
            $sitemapXml = $twig->render('sitemap.xml', array(
                'urls' => $siteMapUrls,
            ));
            $path = $this->outputPath . '/sitemap.xml';
            file_put_contents($path, $sitemapXml);
            echo PHP_EOL . "Sitemap generated to " . $path . ' ' . $this->baseUrl . '/' . basename($path);
        }

        if ($twigLoader->exists('atom.xml')) {
            $atomFeed = $twig->render('atom.xml', array(
                'urls' => $feedUrls,
                'name' => $this->name,
                'author' => $this->author,
                'baseUrl' => $this->baseUrl,
            ));
            $path = $this->outputPath . '/atom.xml';
            file_put_contents($path, $atomFeed);
            echo PHP_EOL . "Atom feed generated to " . $path . ' ' . $this->baseUrl . '/' . basename($path);
        }

        if ($twigLoader->exists('rss.xml')) {
            $atomFeed = $twig->render('rss.xml', array(
                'urls' => $feedUrls,
                'name' => $this->name,
                'author' => $this->author,
                'baseUrl' => $this->baseUrl,
                'description' => $this->description,
            ));
            $path = $this->outputPath . '/rss.xml';
            file_put_contents($path, $atomFeed);
            echo PHP_EOL . "RSS feed generated to " . $path . ' ' . $this->baseUrl . '/' . basename($path);
        }

        return true;
    }
}

