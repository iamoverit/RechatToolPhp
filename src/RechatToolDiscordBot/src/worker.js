const bs = require('nodestalker'),
    tube = 'discordJobs';
const Discord = require("discord.js");
const auth = require("../auth.json");
const discordClient = new Discord.Client();
const fs = require("fs");

const Logger = require("winston");
Logger.remove(Logger.transports.Console);
Logger.add(new Logger.transports.Console, {
    colorize: true
});
Logger.level = 'debug';

discordClient.on('ready', () => {
    Logger.info(`Logged in as ${discordClient.user.tag}!`);
    resJob();
});
discordClient.login(auth.token);

function processJob(job, callback) {
    // doing something really expensive
    console.log('processing...');
    setTimeout(function () {
        callback();
    }, 1000);
}

function resJob() {
    const client = bs.Client('beanstalkd');

    client.watch(tube).onSuccess(function (data) {
        client.reserve().onSuccess(function (job) {
            console.log('received job:', job);
            resJob();

            processJob(job, function () {
                let jobData = JSON.parse(job.data);
                if (jobData.hasOwnProperty('msg')) {
                    let channel = discordClient.channels.get(jobData.msg.channelID);
                    if (jobData.hasOwnProperty('raw_response')) {
                        let jobDataRawResponse = {};
                        try {
                            jobDataRawResponse = JSON.parse(jobData.raw_response);
                        } catch (e) {
                            //skip
                        }
                        if (jobDataRawResponse.hasOwnProperty('tmpfilename')) {
                            const buffer = fs.readFileSync(jobDataRawResponse.tmpfilename);
                            const attachment = new Discord.MessageAttachment(buffer, 'memes.txt');
                            channel.send('<@' + jobData.msg.authorID + '> here are your rechat log!', attachment);
                        } else if (jobData.hasOwnProperty('type')) {
                            if (jobData.type === 'false') {
                                channel.send('<@' + jobData.msg.authorID + '>\n\r' + jobData.raw_response);
                            } else {
                                channel.send('<@' + jobData.msg.authorID + '>```' + jobData.type + '\n\r' + jobData.raw_response + '```');
                            }
                        } else {
                            channel.send('<@' + jobData.msg.authorID + '>```\n\r' + jobData.raw_response + '```');
                        }
                    }
                }

                client.deleteJob(job.id).onSuccess(function (del_msg) {
                    console.log('deleted', job);
                    console.log(del_msg);
                    client.disconnect();
                });
                console.log('processed', job);
            });
        });
    });
}