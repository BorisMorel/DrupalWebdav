<?php
namespace IMAG\davSrv\Nodes;

class NodeContent extends NodeBase
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
        $files = $this->getFiles();
        
        foreach ($files as $file) {
            if ($file->filename == $this->getRoute()->getArgument('content')) {
                return NodeNode::getInstance()
                    ->setRoute($this->getRoute())
                    ->exists();
            }
        }
        
        return false;
    }

    public function getCollectionMembers()
    {
        /**
         * Only resources members
         */
    }

    public function getCreatedAt()
    {
        $file = $this->getFile();

        return new \ezcWebdavDateTime('@'. filectime($file->uri));
    }

    public function getUpdatedAt()
    {
        $file = $this->getFile();

        return new \ezcWebdavDateTime('@'. filemtime($file->uri));
    }

    public function createCollection()
    {
        return false;
    }

    public function createResource()
    {
        /**
         * Because Here I doesn' have the content, all process is handle by setResourceContents()
         */
    }

    public function setResourceContents($content)
    {
        global $user;
        
        $n = NodeNode::getInstance()
            ->setRoute($this->getRoute())
            ->exists()
            ;
        
        $node = node_load($n->nid);

        if (!node_access('update', $node) || !node_access('create', $node)) {
            throw new \ezcWebdavInconsistencyException('Bad Credentials');
        }

        $fileField = $this->getDbFileField();
        $instance = field_info_instance('node',
                                        $fileField->field_name,
                                        $this->getRoute()->getArgument('node_type')
        );
        
        $filename = $this->getRoute()->getArgument('content');
        $file = array(
            'filename' => $filename,
            'uri'      => 'public://'.$filename,
            'filemime' => file_get_mimetype($filename),
            'status'   => 1,
            'display'  => true,
            'uid'      => $user->uid,
        );

        $file = (object) $file;

        $fve = file_validate_extensions($file, $instance['settings']['file_extensions']);
        
        if (true === $this->checkExten && false === empty($fve)) {
            throw new \ezcWebdavInconsistencyException('Bad file extension');
        }

        if ($fileExists = $this->fileExists()) {
            $file->uri = 'public://'.$this->fileNameNewVersion($fileExists);
        }

        file_put_contents($file->uri, $content);
        $savedFile = file_save($file);

        foreach ($node->{$fileField->field_name}['und'] as $key => $file) {

            $pos = $key + 1;

            if ($file['filename'] == $savedFile->filename) {
                $pos = $key;
                break;
            } 
        }
        
        $node->{$fileField->field_name}['und'][isset($pos) ? $pos : 0] = (array) $savedFile;
        $node->revision = true;

        node_save($node);
    }

    public function getResourceContents()
    {
        $file = $this->getFile();
        
        return file_get_contents($file->uri);
    }

    public function delete()
    {
        $n = $this->exists();

        if (!$node = node_load($n->nid)) {
            throw new \ezcWebdavInconsistencyException('Unable to load node');
        }
        
        $fileField = $this->getDbFileField();
        
        $file = $this->getFile();

        if(file_delete($file, true)) {
            foreach ($node->{$fileField->field_name}['und'] as $key => $nodeFile) {
                if ($nodeFile['filename'] != $file->filename) {
                    continue;
                }
                
                unset($node->{$fileField->field_name}['und'][$key]);
            }
            
            node_save($node);
        }
    }

    public function copy(NodeInterface $to)
    {
        
    }

    public function getContentLength()
    {
        $file = $this->getFile();

        return (string) filesize($file->uri);
    }

    private function getFile()
    {
        $query = db_select('file_managed', 'fm');
        $file = $query
            ->fields('fm')
            ->condition('fm.filename', $this->getRoute()
                        ->getArgument('content'), 'LIKE')
            ->orderBy('fid', 'DESC')
            ->execute()
            ->fetchObject()
            ;

        
        return file_load($file->fid);
    }


    private function fileNameNewVersion(\stdClass $file)
    {
        $pathInfo = pathinfo($file->uri);
        $fileInfo = pathinfo($file->filename);
        $counter = 1;

        if (preg_match('/_([\d]+)$/', $pathInfo['filename'], $matches)) {
            $counter = $matches[1] + 1;
        }


        return(sprintf('%s_%s.%s', $fileInfo['filename'], $counter, $fileInfo['extension']));
    }

    private function fileExists()
    {
        $query = $this->getFile();

        return $query ? $query : false;
    }
}