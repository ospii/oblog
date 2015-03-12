<?php
namespace Oblog;

class Blog
{
    /**
     * @var Source of MD files.
     */
    private $sourcePath;

    /**
     * @var Output path.
     */
    private $outputPath;

    /**
     * @var Twig template path
     */
    private $templatePath;

    /**
     * @var Base url for site.
     */
    private $baseUrl;

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

        $loader = new \Twig_Loader_Filesystem($this->templatePath);
        $twig = new \Twig_Environment($loader);

        $siteMapUrls = array();
        /* @var $post \Oblog\Post */
        foreach ($posts as $key => $post) {
            $postHtml = $twig->render('post.html', array(
                'title'      => $post->getTitle(),
                'modifiedAt' => $post->getModifiedAt(),
                'post'       => $post->getHtml(),
            ));

            $pageVariables = array(
                'article' => $postHtml,
                'links' => $links,
                'title' => $post->getTitle()
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
                    'lastmod' => date('Y-m-d', $post->getModifiedAt()));
            } else {
                echo PHP_EOL . 'DRAFT at ' . $this->baseUrl . '/' . $outputFilename;
            }
        }

        if ($lastPostKey !== false) {
            $siteMapUrls[] = array(
                'loc'        => $this->baseUrl . '/',
                'priority'   => '1',
                'lastmod'    => date('Y-m-d', $posts[$lastPostKey]->getModifiedAt()),
                'changefreq' => 'daily',
             );
        }
        $siteMapVariables = array(
            'baseurl' => $this->baseUrl,
            'urls' => $siteMapUrls,
        );
        $sitemapXml = $twig->render('sitemap.xml', $siteMapVariables);
        file_put_contents($this->outputPath . '/sitemap.xml', $sitemapXml);

        return true;
    }
}

