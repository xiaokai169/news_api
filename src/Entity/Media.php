<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Siganushka\MediaBundle\Entity\Media as BaseMedia;
use Siganushka\MediaBundle\Repository\MediaRepository;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media extends BaseMedia
{

}
