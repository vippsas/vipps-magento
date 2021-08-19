#!groovy

@Library('platform-jenkins-pipeline') _

pipeline {
    agent { label 'magento24' }
    options {
        ansiColor('xterm')
    }
    stages {
        stage('Checkout Module') {
            steps {
                git branch: '$BRANCH_NAME', url: 'git@bitbucket.org:vaimo/module-vipps-payment.git'
                sh 'composer install --no-ansi'
            }
        }
        stage('PHP Unit') {
            steps {
                sh './vendor/bin/phpunit -c phpunit.xml'
            }
        }
    }

    post {
        always {
            sendNotifications()
        }
    }
}
