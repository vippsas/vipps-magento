#!groovy

@Library('platform-jenkins-pipeline') _

pipeline {
    agent { label 'magento2' }

    stages {
        stage('PHP Mess Detector') {
            steps {
                sh 'composer install --no-ansi'
                sh './vendor/phpmd/phpmd/src/bin/phpmd . text phpmd.xml'
            }
        }
        stage('Build Module') {
            steps {
                buildModule('magento2-module')
            }
        }
    }

    post {
        always {
            sendNotifications()
        }
    }
}
