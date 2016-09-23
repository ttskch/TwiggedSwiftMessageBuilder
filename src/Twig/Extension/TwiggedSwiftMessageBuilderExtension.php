<?php
namespace Ttskch\TwiggedSwiftMessageBuilder\Twig\Extension;

use Ttskch\TwiggedSwiftMessageBuilder\ImageEmbedder\Embedder;

class TwiggedSwiftMessageBuilderExtension extends \Twig_Extension
{
    private $embedder;

    public function __construct(Embedder $embedder)
    {
        $this->embedder = $embedder;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('embed_image', array($this, 'embedImage')),
        );
    }

    public function embedImage($imagePath)
    {
        return $this->embedder->placeholderize($imagePath)->getPlaceholder();
    }

    public function getName()
    {
        return 'tch_twigged_swiftmessage_builder_extension';
    }
}
