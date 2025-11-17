# V-League 스카우팅 시스템 - 프론트엔드 프로토타입
**Volleyball Player Scouting Support System - Frontend Prototype (HTML/CSS)**

## 1. 해당 폴더

프로젝트의 **페이지 흐름**과 **UI/UX 디자인**을 시각적으로 공유하기 위함

**HTML/CSS와 부트스트랩(Bootstrap)만으로 제작한 '클릭 가능한' 프로토타입(Mockup)**

(PHP 및 DB 연동 전입니다.)

## 2. 라이브 데모 (Netlify)

아래 링크를 클릭하면 폰이나 컴퓨터에서 **실제 웹사이트처럼** 이 프로토타입을 **직접 클릭**해볼 수 있음

**[프로토타입 데모 링크](https://splendid-sunshine-d28e9f.netlify.app/login.html)**
*(시작 페이지는 `login.html` 입니다.)*

## 3. 프로토타입의 주요 특징 (교수님 요구사항 반영)

* **1. 최소 8페이지 이상:** `login`부터 `analysis`까지 **총 9개의 페이지** 흐름을 구현

* **2. 다양한 UI 컨트롤:** 교수님이 강조하신 **다양한 컨트롤**을 각 분석 페이지에 배치
    * `analysis_value` (랭킹): **라디오 버튼** + **텍스트 박스**
    * `analysis_team_killer` (집계): **드롭다운 메뉴**
    * `analysis_compare` (윈도우): **드롭다운 메뉴 x2**
    * `analysis_form` (OLAP): **체크박스** + **드롭다운 메뉴**
    * `player_profile` (CRUD): **텍스트 영역(Text Area)**

* **3. 4대 분석 + 3대 기능:** 4대 고급 분석, CRUD, 세션, 트랜잭션 페이지의 UI를 모두 설계

## 4. 페이지 구성 (총 9개)

| 페이지 (목적) | 파일명 | UI 컨트롤 |
| :--- | :--- | :--- |
| **(세션)** 로그인 | `login.html` | 텍스트 박스, 버튼 |
| **(허브)** 메인 대시보드 | `dashboard.html` | 내비게이션 바, 카드 메뉴 |
| **(Ranking)** 가성비 랭킹 | `analysis_value.html` | **라디오 버튼**, 텍스트 박스 |
| **(Aggregates)** 팀 킬러 찾기 | `analysis_team_killer.html` | **드롭다운 메뉴** |
| **(Windowing)** 선수 성적 비교 | `analysis_compare.html` | **드롭다운 메뉴 x2** |
| **(OLAP)** 선수 폼 분석 | `analysis_form.html` | **체크박스**, 드롭다운 |
| **(CRUD - C/R)** 선수 정보 | `player_profile.html` | 텍스트 영역, 버튼 |
| **(CRUD - U/D)** 내 노트 | `my_notes.html` | 버튼 (수정/삭제) |
| **(Transaction)** 가상 트레이드 | `trade_center.html` | 드롭다운 메뉴 x2, 버튼 |

## 5. 로컬에서 실행하는 법

1.  `git clone` or 'Download ZIP'
2.  폴더에서 `login.html` 파일을 선택 후 테스트

* **GitHub:** `Ohjisong`
* **Date:** 2025-11-01
* **Note:** 본 프로토타입은 팀 프로젝트 기획 및 UI 시각화를 위해 제작되었습니다.
