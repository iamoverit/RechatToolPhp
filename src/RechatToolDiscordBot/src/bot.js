const Discord = require("discord.js");
const auth = require("../auth.json");
// const Beanstalkd = require('beanstalkd');
const fs = require("fs");
const utf8 = require('utf8');

const Logger = require("winston");
Logger.remove(Logger.transports.Console);
Logger.add(new Logger.transports.Console, {
    colorize: true
});
Logger.level = 'debug';

const Jackd = require('jackd');
const beanstalkd = new Jackd();
beanstalkd.connect({'host': 'localhost', 'port': 11300});
// const beanstalkdClient = new Beanstalkd.constructor('localhost', 11300);
// Configure logger settings


const client = new Discord.Client();

client.on('ready', () => {
    Logger.info(`Logged in as ${client.user.tag}!`);
});

client.on('message', async msg => {
    // Our bot needs to know if it will execute a command
    // It will listen for messages that will start with `!`
    let message = msg.content;
    if (message.substring(0, 1) === '!') {
        let args = message.substring(1).split(' ');
        let cmd = args[0];

        args = args.splice(1);
        switch (cmd) {
            case 'ping':
                msg.reply('Pong!');
                break;
            case 'meme':
                msg.channel.send('Rechat of ' + args.join(' '));
                const buffer = fs.readFileSync('./memes.txt');
                const attachment = new Discord.MessageAttachment(buffer, 'memes.txt');
                msg.channel.send(`${msg.author}, here are your memes!`, attachment);
                //msg.reply(new Discord.MessageAttachment('Rechat', 'messages.log'));
                break;
            case 'rechat':
            case 'r':
                Logger.info({'msg': JSON.parse( utf8.encode(JSON.stringify( msg )) )});
                msg.channel.send('Waiting for rechat of `' + args.join(' ') + '`');
                await beanstalkd.use('rechatJobs');
                await beanstalkd.put({'msg': JSON.parse( utf8.encode(JSON.stringify( msg )) )});
                break;
            default:
                //msg.channel.send('command `' + args.join(' ') + ' applied`');
                await beanstalkd.use('toolsJobs');
                await beanstalkd.put({'msg': JSON.parse( utf8.encode(JSON.stringify( msg )) )});
                break;
            // Just add any case commands if you want to..
        }
    }
});

// const beanstalkdWorker = new Jackd();
// beanstalkdWorker.connect({'host': 'localhost', 'port': 11300});
// beanstalkdWorker.use('discordJobs');
// beanstalkdWorker.watch('discordJobs');
// async function worker() {
//     while (true) {
//         try {
//             const {id, payload} = await beanstalkdWorker.reserveWithTimeout(5);
//             /* ... process job here ... */
//             Logger.info(payload);
//             await beanstalkdWorker.delete(id);
//         } catch (err) {
//             // Log error somehow
//             console.error(err);
//         }
//     }
// }
//
// worker().then(v => {Logger.log('worker started')});

client.login(auth.token);