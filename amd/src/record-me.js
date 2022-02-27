// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/*
 * @package    assignfeedback_audio
 * @copyright  Mukudu Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 /**
  * @module assignfeedback_audio/record-me
  */
define(['jquery', 'core/str', 'core/notification'], function($, str, notification) {
    return {
        init: function(posturl, maxduration, gradeid, contextid) {
            if (navigator.mediaDevices.getUserMedia) {
                // Get all required strings.
                var notificationok;
                var notificationtitle;
                var stoprecording;
                var stoplistening;
                var maxtimeallowedreached;
                var feedbackremoved;
                var nomediarecordingsupport;
                var nomicrophineallowed;
                var mediasavefailed;
                var medianocapture;
                str.get_strings([
                    {'key' : 'ok', component : 'assignfeedback_audio'},
                    {'key' : 'pluginname', component : 'assignfeedback_audio'},
                    {'key' : 'stoprecordingprompt', component : 'assignfeedback_audio'},
                    {'key' : 'stoplisteningprompt', component : 'assignfeedback_audio'},
                    {'key' : 'maxtimeallowedreached', component : 'assignfeedback_audio'},
                    {'key' : 'feedbackremoved', component : 'assignfeedback_audio'},
                    {'key' : 'nomediarecordingsupport', component : 'assignfeedback_audio'},
                    {'key' : 'nomicrophineallowed', component : 'assignfeedback_audio'},
                    {'key' : 'mediasavefailed', component : 'assignfeedback_audio'},
                    {'key' : 'medianocapture', component : 'assignfeedback_audio'}
                ]).done(function(strs) {
                    notificationok = strs[0];
                    notificationtitle = strs[0];
                    stoprecording = strs[2];
                    stoplistening = strs[3];
                    maxtimeallowedreached = strs[4];
                    feedbackremoved = strs[5];
                    nomediarecordingsupport = strs[6];
                    nomicrophineallowed = strs[7];
                    mediasavefailed = strs[8];
                    medianocapture = strs[9];
                });

                var nopermission = false;
                // Check permissions - not supported by all browsers - so we ignore those.
                navigator.permissions.query({name:'microphone'})
                    .then(function(result) {
                        if (result && result.state == 'denied') {
                            nopermission = true;
                            showfeedbackinfo(nomicrophineallowed);
                        }
                     })
                     .catch(function () {
                     });

                var showfeedbackinfo = function(msg) {
                    $(".feedbackinfoarea").css('display', 'block');
                    $(".feedbackinfoarea").text(msg);
                };

                var processResponse = function(data) {
                    if (data.status == 'error') {
                        notification.alert(notificationtitle, data.message, notificationok) ;
                    } else {
                        $("#id_fileref").val(data.fileref);
                    }
                };

                var saveRecordingFile = function() {
                    if (recordedChunks.length > 0) {
                        audioBlob = new Blob(recordedChunks, {type: audiotype});
                        // Send file to server.
                        var fd = new FormData();
                        fd.append('action', 'save');
                        fd.append('audiofile', audioBlob);
                        fd.append('gradeid', gradeid);
                        fd.append('contextid', contextid);
                        $.ajax(posturl, {
                            method: 'POST',
                            contentType: false,
                            processData: false,
                            dataType: 'json',
                            data: fd,
                            error: function() {
                                notification.alert(notificationtitle, mediasavefailed, notificationok) ;
                            },
                            success: processResponse
                        });
                    } else {
                         notification.alert(notificationtitle, medianocapture, notificationok) ;
                    }
                };
                var recordedChunks = [];
                var audiotype = 'audio/webm';
                var audioBlob;
                var audio;
                var mediaRecorder;
                var cliplength = 15;    // Seconds
                // Arrgghhhh!!!!  For some reason .prop() and .attr() not working.
                var startbtn = document.getElementsByName("startbtn")[0];
                if (!nopermission) {
                    startbtn.disabled = false;
                }
                var stopbtn = document.getElementsByName("stopbtn")[0];
                var listenbtn = document.getElementsByName("listenbtn")[0];
                var deletebtn = document.getElementsByName("deletebtn")[0];

                $(".startbtn").click(function() {
                    startbtn.disabled = true;
                    stopbtn.disabled = false;
                    listenbtn.disabled = true;
                    deletebtn.disabled = true;
                    if ( recordedChunks.length > 0 ) {
                        recordedChunks = [];
                    }
                    // start recording.
                    navigator.mediaDevices.getUserMedia({ audio: true })
                        .then(function(stream) {
                            mediaRecorder = new MediaRecorder(stream, {mimeType: audiotype});
                            mediaRecorder.addEventListener('dataavailable', function(e) {
                                if (e.data.size > 0) {
                                    recordedChunks.push(e.data);
                                    // Check length of clip - each chunk is 15 secords
                                    if (recordedChunks.length >= (maxduration/cliplength)) {
                                        notification.alert(notificationtitle, maxtimeallowedreached, notificationok) ;
                                        $(".stopbtn").click();
                                    }
                                }
                            });
                            mediaRecorder.addEventListener('stop', saveRecordingFile);
                            mediaRecorder.start(1000 * cliplength);    //Every 15 seconds
                          });
                });

                $(".stopbtn").click(function() {
                    stopbtn.disabled = true;
                    startbtn.disabled = false;
                    listenbtn.disabled = false;
                    deletebtn.disabled = false;
                    if (mediaRecorder.state == 'recording') {
                        mediaRecorder.stop();
                    } else {                // we are listening
                        if (audio.src) {
                            audio.pause();
                        }
                    }
                    // Stop recording.
                    stopbtn.textContent = stoprecording;
                });

                $(".listenbtn").click(function() {
                    listenbtn.disabled = true;
                    stopbtn.disabled = false;
                    startbtn.disabled = true;
                    deletebtn.disabled = true;
                    stopbtn.textContent = stoplistening;
                    // Listen recording
                    var audioUrl = URL.createObjectURL(audioBlob);
                    audio = new Audio(audioUrl);
                    audio.addEventListener("canplaythrough", function() {
                        /* the audio is now playable; play it if permissions allow */
                        audio.play();
                    });
                    audio.addEventListener("ended", function() {
                        $(".stopbtn").click();
                    });
                });

                $(".deletebtn").click(function() {
                    startbtn.disabled = false;
                    listenbtn.disabled = true;
                    stopbtn.disabled = true;
                    deletebtn.disabled = true;
                    // Delete the file and the clip;
                    recordedChunks = [];
                    audioBlob = null;
                    $("#id_fileref").val('');
                    notification.alert(notificationtitle, feedbackremoved, notificationok) ;
                });
            } else {
                showfeedbackinfo(nomediarecordingsupport);
            }
        }
    };
});
