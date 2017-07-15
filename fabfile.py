#this fabfile is written in order to automate the deployment process of Meddream viewer.
#LAMP stack is needed which eruires php version 5.6
#both nginx and apache will be running in this project and apache will be runinng in port 8080

from fabric.api import *
from fabric.operations import *
from fabric.contrib.project import rsync_project
from fabric.contrib.files import exists

import sys,os
sys.dont_write_bytecode = True

abspath = lambda filename: os.path.join(
    os.path.abspath(os.path.dirname(__file__)),
    filename
)

# --------------------------------------------
# Meddream viewer cofiguration
# --------------------------------------------

class FabricException(Exception):
    pass

#fab app install deploy

def app():
    print "Connecting to Server"

    env.setup = True
    env.user = 'ubuntu'
    env.ubuntu_version = '16.04'
    env.warn_only = True
    #env.password = 'SSH_PASSWORD'
    env.key_filename = abspath('/home/ubuntu/keyfile/key.pem')
    env.abort_exception = FabricException

    env.hosts = [
        'A.B.C.D'   #server_ip_address
    ]

    env.graceful = False
    env.is_grunt = True
    env.output_prefix = False
    env.home = '/home/%s' %(env.user)
    env.project = 'meddream'
    env.viewer_path = '/var/www/%s' %(env.project)
    #local config file path
    env.server = 'localhost' #used in apache config file

    env.apache_config_path = '/etc/apache2/apache2.conf'
    env.apache_directory_path = '/etc/apache2/mods-enabled'
    env.apache_directory_config = abspath('devops/dir.conf')
    env.apache_port_config = abspath('devops/ports.conf')
    env.nginx_config = 'devops/viewer_ng.conf'
    env.apache_config = 'devops/viewer_ap.conf'
    env.php_config = '/etc/php/5.6/apache2/php.ini'

    env.rsync_exclude = [
       "*.py",
       "*.pyc",
       "*.conf",
       "how_to_install",
       "devops/*",
       "devops"
    ]

    return


def install():
    update()
    install_apache()
    install_nginx()
    install_mysql()
    install_php()
    config_php_settings()
    config_nginx()
    config_apache()

def install_apache():
    print 'Installing and configuring apache web server'
    sudo('apt-get install apache2 -y')
    sudo("apache2ctl configtest")
    sudo('echo "ServerName %s" >> %s' %(env.server, env.apache_config_path))
    sudo("apache2ctl configtest")

    default_config = '/etc/apache2/mods-enabled/dir.conf'
    if exists(default_config):
        sudo('rm %s' %default_config)
        print 'Deleted apache default directory config'
    print 'Install new apache directory configuration'
    put('%s' % (env.apache_directory_config), '%s/' % (env.apache_directory_path), use_sudo=True)

    default_config = '/etc/apache2/ports.conf'
    if exists(default_config):
        sudo('rm %s' %default_config)
        print 'Deleted apache default port config'
    put('%s' % (env.apache_port_config), '/etc/apache2/', use_sudo=True)
    sudo('systemctl restart apache2')

def install_nginx():
    print 'Installing NGINX'
    sudo("apt-get install -y nginx")
    sudo('systemctl enable nginx.service')
    default_config = '/etc/nginx/sites-enabled/default'
    if exists(default_config):
        sudo('rm %s' %(default_config))
        print 'Deleted NGINX default config'
    sudo('systemctl start nginx.service')

def install_mysql():
    print 'Installing and configuring mysql'
    sudo('apt-get install mysql-server -y')
    sudo('mysql_secure_installation')

def install_php():
    print "Installing php dependency packages"
    sudo('apt-get purge php.*')
    sudo('add-apt-repository ppa:ondrej/php')
    sudo('apt-get install software-properties-common -y')
    sudo('apt-get update')
    sudo('apt-get install php5.6 -y')
    sudo('apt-get install php5.6-mbstring php5.6-mcrypt php5.6-mysql php5.6-xml php5.6-gd -y')

def config_php_settings():
    print 'Configure php setting for viewer'
    default_extension = 'extension=/var/www/%s/php5.6_meddream-x86_64.so' %(env.project)
    sudo('echo "%s" >> %s' %(default_extension, env.php_config))
    sudo('apache2ctl configtest')
    sudo('systemctl restart apache2')

def config_nginx():
    print 'Configuring NGINX'
    put('%s' % (env.nginx_config), '/etc/nginx/sites-enabled/', use_sudo=True)
    sudo('nginx -t')
    sudo('systemctl restart nginx')

def config_apache():
    print 'Configuring apache web server'
    put('%s' % (env.apache_config), '/etc/apache2/sites-enabled/', use_sudo=True)
    sudo('apache2ctl configtest')
    sudo('systemctl restart apache2')

def update():
    print 'Start updating the system'
    sudo('apt-get update')


# -------------------------------------------
# Deploying Meddream viewer
# --------------------------------------------

def deploy():
    print 'Starting to deploy Viewer'
    sync_code_base()
    change_ownership()
    sudo('systemctl restart apache2')
    print 'Finished the deployment of Viewer'

def sync_code_base():
    print 'Syncing Viewer code base'
    sudo('chown %s:www-data /var/www' %(env.user))
    sudo('mkdir -p %s' %(env.viewer_path))
    sudo('chown %s:www-data %s' %(env.user, env.viewer_path))
    rsync_project(env.viewer_path, abspath('meddream/') + '*', exclude=env.rsync_exclude, delete=True, default_opts='-rvz')

def change_ownership():
    print 'Changing ownership of viewer'
    sudo('chown -R %s:www-data %s' %(env.user, env.viewer_path))
    sudo('chmod 0777 %s' %(env.viewer_path))
    sudo('chmod 0777 %s/temp' %(env.viewer_path))
    sudo('chmod 0777 %s/log' %(env.viewer_path))
    sudo('chmod a+x %s/dcm4che/bin/*' %(env.viewer_path))
    sudo('chmod a+x %s/*.sh' %(env.viewer_path))
