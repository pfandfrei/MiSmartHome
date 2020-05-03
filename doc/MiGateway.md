## MiGateway

Description: Wall outlet

Device ID: 0x286c

Model: gateway

## Channel 0 

### Device Configuration Parameters

#### SID

Unique Device ID

|  |  |
| -------------- | ------ |
| Type      | String |
| Readable  | Yes   |
| Writeable | No    |
| Default Value |   |

#### HEARTBEAT

Timestamp of last heartbeat message

|                   |         |
| ----------------- | ------- |
| Type          | Integer |
| Readable  | Yes   |
| Writeable | No    |
| Default Value |         |

#### IP_ADDRESS

IP address of the gateway

|                   |         |
| ----------------- | ------- |
| Type          | String |
| Readable  | Yes   |
| Writeable | Yes    |
| Default Value |         |

#### PORT

port used by gateway 

|                   |         |
| ----------------- | ------- |
| Type          | Integer |
| Readable  | Yes   |
| Writeable | Yes    |
| Default Value | 9898    |
| Minimum Value | 1    |
| Maximum Value | 65535   |

#### PROTO_VERSION

protocol version

|                   |         |
| ----------------- | ------- |
| Type         | String |
| Readable  | Yes   |
| Writeable | No    |
| Default Value |         |

#### PASSWORD

password for api communication

|                   |         |
| ----------------- | ------- |
| Type        | String |
| Readable  | Yes   |
| Writeable | Yes    |
| Default Value |         |

#### DEBUG_LEVEL

debug level 

|                   |         |
| ----------------- | ------- |
| Type          | Integer |
| Readable  | Yes   |
| Writeable | Yes    |
| Default Value | 0    |
| Minimum Value | 0    |
| Maximum Value | 5   |

## Channel 1

### Variables

#### ILLUMINATION

ambient illumination

|           |                     |
| -------------- | :----------------------------- |
| Type          | Integer                    |
| Readable      | Yes                            |
| Writeable     | No                          |
| Minimum Value | 0                              |
| Maximum Value | 1300 |

#### RGB

color of the led ring

|           |                     |
| -------------- | :----------------------------- |
| Type          | Integer                    |
| Readable      | Yes                            |
| Writeable     | Yes                        |
| Minimum Value | 0                              |
| Maximum Value | 16777215 |

#### BRIGHTNESS

brightness of the led ring in percent

|           |                     |
| -------------- | :----------------------------- |
| Type          | Integer                    |
| Readable      | Yes                            |
| Writeable     | Yes                        |
| Unit      | %     |
| Minimum Value | 0                              |
| Maximum Value | 100 |

#### ENABLE

enable led ring with last values of RGB and BRIGHTNESS

|           |       |
| --------- | :---- |
| Type      | Boolean |
| Readable  | Yes   |
| Writeable | Yes    |

#### RGB_OLD

internal store last color of the led ring

|           |                     |
| -------------- | :----------------------------- |
| Type          | Integer                    |
| Readable      | No                            |
| Writeable     | No                        |
| Minimum Value | 0                              |
| Maximum Value | 1694498815 |

## Channel 2

### Variables

#### MUSIC_ID

select preinstalled music or custom mp3 (value >=10001) 
custom mp3 must be uploaded from app
a value of 10000 will stop playing. You should set PLAY to FALSE  for that

|           |                     |
| -------------- | :----------------------------- |
| Type          | Enumeration                    |
| Readable      | Yes                            |
| Writeable     | Yes                          |
| Values        | 0 Police siren<br />1 Police siren 2<br />2 Accident tone<br />3 Missle countdown<br />4 Ghost<br />5 Sniper<br />6 War<br />7 Air Strike<br />8 Barking dogs<br />10 Doorbell ring tone<br />11 Knock on door<br />12 Hilarious<br />13 Alarm clock<br />20 MiMix<br />21 Enthusiastic<br />22 GuitarClassic<br />23 IceWorldPiano<br />24 LeisureTime<br />25 Childhood<br />26 MorningStreamlet<br />27 MusicBox<br />28 Orange<br />29 Thinker |

#### VOLUME

set volume level

|           |                     |
| -------------- | :----------------------------- |
| Type          | Integer                    |
| Readable      | Yes                            |
| Writeable     | Yes                        |
| Minimum Value | 0                              |
| Maximum Value | 100 |

#### PLAY

plays or stops selected MUSIC_ID

|           |       |
| --------- | :---- |
| Type      | Boolean |
| Readable  | Yes   |
| Writeable | Yes    |

#### MUSIC_ID_OLD

internal store last MUSIC_ID

|           |                     |
| -------------- | :----------------------------- |
| Type          | Integer                    |
| Readable      | No                            |
| Writeable     | No                        |

