Run the following commands to start the Docker engine:

`docker compose up --build` ← only the first time, installs nette dependencies

`docker compose up` ← all other times 
___
The website should be available at the following address:

`localhost:8080`

You can access the containers with the following command:

`docker exec -ti meta-v-app-1 /bin/bash`

`docker exec -ti meta-v-mysql-1 /bin/bash`