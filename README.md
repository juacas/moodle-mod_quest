# mod_questournament
The QUESTOURnament module allows teachers to configure both individual and group work environments where a set of intellectual challenges are launched by teachers in order to be solved by students in a time-constrained way. Challenges can be also submitted, and the corresponding answers pre-evaluated, by the students, in which case, the teacher must approve the challenge before it is presented to the rest of students. In this case, the student will be rewarded depending on the quality of his/her work, including both the adequateness of the proposed challenge and the assessment of answers submitted by the other students to the challenge proposed by him/her. QUESTOURnament makes use of a variable scoring system (please see Figure 1). Since the time when a challenge is created until the end of the process, the challenge goes through different phases:

- Stationary phase: score remains as proposed by the teacher during a period of time to allow students to understand and to take in the task.
- Inflationary phase: score grows to adjust the reward to the difficulty level of the challenge. It is assumed that a lack of correct answers means that the difficulty is higher than the reward.
- Deflationary phase: once a challenge is correctly answered, the score starts decreasing so that the student who is the first to answer gets the maximum score.

On the other hand, challenges can be on a set of states as follows:

- Approval pending: the challenge has been proposed by a student but a teacher has not approved it yet.
- Start pending: each challenge has a start date and an end date. Answers can be received only during the intermediate period. Besides, before the start date, only who proposed the challenge and/or the teacher can access it.
- In progress: the challenge is fully active, answers are received and scoring is varying.
- Closed: time to answer is over, and no more answers are allowed. Students can read all submissions.


The QUESTOURnament module shows all the time the current summarized ranking with a direct access to a detailed scoring board. The classifications can be individual and per team, in case teams have been organized among the students. 

As a result, the QUESTOURnament module is a dynamic and changing environment in which the students are content generators and participate in the learning process in an active way.

It is available in many languages: full translated to spanish and english and partial translation to german, frech, italian, catalan, galego and euskara. (Traslators are welcome.)

Installation
=============

1. unzip, and copy into Moodle's /mod folder
2. visit administration page to install module
3. configure default settings for your site
4. use in any course as wished

Change log
==========
 - v1.4.8 Fix assessment when ther's just one assessment element.
 - v1.4.6 Fix filtering of description field for assessment elements.
 - v1.4.5 Link in notification emails fixed.
 - v1.4.4 Some translation strings fixed.
 - v1.4.3 Help strings reformatted.
 - v1.4.2 Lang strings cleaned for translation with AMOS
 - v1.4.1 Code refactoring and cleaning to conform to Moodle's development guides.
 - v1.3.7 Implement activity completion (gradepass).
 - v1.3.6 Notification messages grouped for starting Challenges.
 - v1.3.5 Remove Moodle 1.x support.
 - v1.3.4 Export scores as CSV.
 - v1.3.3 Enforce sesskeys. Assessment workflow enhanced. Minimum and maximum points customizable.
 - v1.3.1 Compatibility with Moodle 3.x. Support non-editing teacher role. Add more permissions.
 - v1.3.0 Compatibility with Moodle 2.6. Manage anonymous authors after restore.
 - v1.2.0 Compatibility with Moodle 2.x.

(c) 2013 onwards. EDUVALab. University of Valladolid.

