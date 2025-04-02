## Installation
Clone repository
```bash
git clone https://github.com/Edwardone/ww-crawler.git
```

Open project 
```bash
cd ww-crawler
```

Grant execution permission for ed utility
```bash
chmod +x ed
```

Generate docker-compose.yaml, then chose your OS
```bash
./ed init
```

Create .env
```bash
cp .env.example .env
```

Build containers, enter your password
```bash
./ed build 
```

Success, project should be started!

To enter into php container run
```bash
./ed php 
```

To run command to start scrapping from php container
```bash
pa parse:blog
```

Project should be available on 127.0.0.1

