#!groovy

@Library('platform-jenkins-pipeline') _

pipeline {
    agent { label 'magento23' }
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
        stage('PHP Mess Detector') {
            steps {
                sh './vendor/bin/phpmd . ansi phpmd.xml'
            }
        }
        stage('PHP CS') {
            steps {
                sh './vendor/bin/phpcs'
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
