# Installing Meddream Viewer on Ubuntu EC2 Instance #

MedDream is a web based DICOM Viewer. DICOM is a standard for storing and transmitting medical images. In this repository, I am going to setup meddrream viewer on Ubutu AWS machine.

Meddream is a PHP application and both nginx and apache web server will be used in the scenario. Although NGINX and apache is capable of serving PHP application independently but I have done this staff as a fun. Nginx will reverse proxy requests to apache and apache web server will serve the application. In order to run this script we have to make sure that fabric library is installed in the host machine. To install fabric,

```
$ curl "https://bootstrap.pypa.io/get-pip.py" -o "get-pip.py"
$ python get-pip.py
$ pip install fabric
```

or you can install fabric using package manager.

```
$ sudo apt-get install fabric
```

For aws instance, edit the username, keyfile location and server address in **fabfile.py**. But if you use vagrant or any other server with password based ssh then edit **env.user** value and uncomment **env.password** and set new password value. Finally, comment out the **env.key_filename** field.

```
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
```

Next, we need to also edit the IP address in the nginx configuration file. Edit the **devops/viewer_ng.conf** file and set the IP address of the server.

```
server {
        listen 80;
        server_name A.B.C.D;  #server_ip_address

        location /meddream {	#this portion will handle the requests for meddream web application
                proxy_pass http://127.0.0.1:8080;
                proxy_set_header Host $host;
                proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        }
}
```

Now, run the fabric script and install the application.

```
fab app install deploy
```

Next, browse to the following link to view the application.

```
http://server_ip_address/meddream
```
Here, two web servers are used because nginx will serve another application which will trigger meddream application. 
