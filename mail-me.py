#!/usr/bin/python
import sys, json
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

j = ""
for line in sys.stdin:
    j += line

#text_file = open("Output.txt", "w")
#text_file.write(j)
#text_file.close()

j = json.loads(j)

print(j)



# me == my email address
# you == recipient's email address
me = ""
you = "" # comma-seperated list of addresses

# Create message container - the correct MIME type is multipart/alternative.
msg = MIMEMultipart()
msg['Subject'] = "Python test"
msg['From'] = me
msg['To'] = you

# Create the body of the message (a plain-text and an HTML version).
html = """\
<html>
  <head></head>
  <body>
    <p>Hi %(name)s!<br>this is a python test.</p>
  </body>
</html>
"""


print(html % { "name": "QXSCH" })


# Record the MIME types of both parts - text/plain and text/html.
msg.attach(MIMEText(html % {"name": "QXSCH"}, 'html'))
# Send the message via local SMTP server.
s = smtplib.SMTP('localhost')
# sendmail function takes 3 arguments: sender's address, recipient's address
# and message to send - here it is sent as one string.
s.sendmail(me, you, msg.as_string())
s.quit()

