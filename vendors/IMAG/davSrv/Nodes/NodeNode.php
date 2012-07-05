<?php
namespace IMAG\davSrv\Nodes;

class NodeNode extends NodeBase
{
    private static
        $singleton
        ;

    public static function getInstance()
    {
        if (!isset(static::$singleton)) {
            static::$singleton = new static();
        }

        return static::$singleton;
    }

    public function exists()
    {
        $res = db_select('node', 'n')
            ->fields('n')
            ->condition('n.type', $this->getRoute()
                        ->getArgument('node_type'), 'LIKE')
            ->condition('n.title', $this->getRoute()
                        ->getArgument('node'), 'LIKE')
            ->execute()
            ->fetchObject()
            ;
        
        return $res;
    }

    public function getCollectionMembers()
    {
        $files = $this->getFiles();
        
        foreach($files as $file) {
            $path = $this->getRoute()->getPath().'/'.$file->filename;
            $t[] = new \ezcWebdavResource($path);
        }
        
        return $t;
    }

    public function getCreatedAt()
    {
        $node = $this->exists();

        return new \ezcWebdavDateTime('@'.$node->created);
    }

    public function getUpdatedAt()
    {
        $node = $this->exists();
        
        return new \ezcWebdavDateTime('@'.$node->changed);
    }

    public function createCollection()
    {
        $node = new \stdClass();
        $node->type = $this->getRoute()
            ->getArgument('node_type');
        node_object_prepare($node);
        
        $node->title = $this->getRoute()
            ->getArgument('node');
        $node->revision = true;
        $node->language = 'und';

        node_save($node);
    }

    public function createResource()
    {
        /**
         * Unallowed to create resource for this type
         */
    }

    public function setResourceContents($content)
    {
        /**
         * Unallowed to create resource for this type
         */
    }

    public function getResourceContents()
    {
        /**
         * No Ressource ; only collection
         */
    }

    public function delete()
    {
        $n = $this->exists();
        $node = node_load($n->nid, $n->vid, true); // Just because Drupal 7 have a Fishs !

        if (!node_access('delete', $node)) {
            return array (
                new \ezcWebdavErrorResponse(\ezcWebdavResponse::STATUS_403, $this->getRoute()->getPath(), 'Bad credentials'),
            );
        }

        if ((bool) $node === true) {
            node_delete($node->nid);
        }
    }

    public function copy(NodeInterface $to)
    {
        $n = $this->exists();

        $node = node_load($n->nid, $n->vid, true);

        if (!node_access('update', $node) || !node_access('create', $node)) {
            return array (
                new \ezcWebdavErrorResponse(\ezcWebdavResponse::STATUS_403, $this->getRoute()->getPath(), 'Bad credentials'),
            );
        }
        
        $node->is_new = true;
        unset(
            $node->nid,
            $node->vid
        );
        $node->title = $to->getRoute()
            ->getArgument('node');

        
        node_save($node);
    }
}