# RechatToolPhp
twitch rechat utility on php with discord bot on nodejs


* first of all you need to install [**beanstalkd**](https://beanstalkd.github.io/download.html) and [**mongodb**](https://docs.mongodb.com/manual/installation/)
* create auth.json file inside `src/RechatToolDiscordBot` folder with your bot token like this:
```json
{
  "token": "your-discord-bot-token"
}
```

* run `composer install` inside root folder
* run `npm install` inside `src/RechatToolDiscordBot` folder
* rename [`.env.example`](.env.example) to `.env` and put your env variables to there 
  * BEANSTALKD_HOST and BEANSTALKD_PORT
  * TWITCH_APP_CLIENT_ID and TWITCH_APP_CLIENT_SECRET if nessessary 
* run `php src/console.php rechat:worker` - to listen **rechatJobs** queue in beanstalkd and process them and put response into **discordJobs**
* run `php src/console.php tools:worker` - to listen **toolsJobs** queue in beanstalkd and process them and put response into **discordJobs**
* run `nodejs src\RechatToolDiscordBot\src\bot.js` to listen commands from discord and put them into **rechatJobs** and **toolsJobs** queues
* run `nodejs src\RechatToolDiscordBot\src\worker.js` to listen commands from `discordJobs` queue and reponse them to discord

example installation script:
```bash
git clone https://github.com/iamoverit/RechatToolPhp.git
cd RechatToolPhp
echo '{"token": "your-discord-bot-token"}' > src/RechatToolDiscordBot/auth.json
composer install
cp .env.example .env
cd src/RechatToolDiscordBot
npm install
cd ..
php src/console.php rechat:worker > /dev/null &
php src/console.php tools:worker > /dev/null &
nodejs src/RechatToolDiscordBot/src/bot.js > /dev/null &
nodejs src/RechatToolDiscordBot/src/worker.js > /dev/null &
```
