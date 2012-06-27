<?php

class WebdavDrupalAuth implements ezcWebdavBasicAuthenticator, ezcWebdavAuthorizer
{
    private
        $router
        ;
    
    public function __construct(WebdavDrupalRouterInterface $router)
    {
        $this->router = $router;
    }

    public function authenticateAnonymous(ezcWebdavAnonymousAuth $data)
    {
        return false;
    }

    public function authenticateBasic(ezcWebdavBasicAuth $data)
    {
        global $user;

        if ($uid = user_authenticate($data->username, $data->password)) {
            $user = user_load($uid);
            if (user_access('Webdav access') === true) {
                return true;
            }
        }

        return false;        
    }

    public function authorize($user, $path, $access = ezcWebdavAuthorizer::ACCESS_READ)
    {
        return true;
    }

}
